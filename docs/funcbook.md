## Functionality Book

This document describes how this application works.

### Scope

- Focus: Docker-based per-instance orchestration, Traefik routing, ForwardAuth, and the user/admin surfaces and CLI that control them.
- The general web application (auth, templating) is conventional Symfony and not the main subject here.

## High-level architecture (Docker + Traefik)

- **Entity**: `App\McpInstances\Domain\Entity\McpInstance`
  - Persists one per account (at most) with screen settings and per-instance secrets: `vncPassword` and `mcpBearer`.
  - Derived fields: `instanceSlug`, `containerName`, `mcpSubdomain`, `vncSubdomain`, and `containerState`.
- **Domain service**: `App\McpInstances\Domain\Service\McpInstancesDomainService`
  - Creates/restarts/stops instances, persists state, and delegates to the Docker facade for lifecycle.
- **Docker facade/service**:
  - `App\DockerManagement\Facade\DockerManagementFacade` + `App\DockerManagement\Domain\Service\ContainerManagementDomainService` manage container lifecycle via a controlled Docker CLI wrapper.
  - Labels for Traefik are generated at container creation time to expose per-instance subdomains.
- **Reverse proxy**: Traefik (container)
  - Terminates TLS for all domains/subdomains, routes `app.*` → host nginx:8090 and per-instance subdomains → instance containers.
  - Enforces MCP bearer via ForwardAuth middleware to the Symfony endpoint.
- **Host web server**: nginx on 8090 (no TLS)
  - Serves the native Symfony app; fronted by Traefik on 80/443.

## Lifecycle of an MCP instance

### Create (user-driven)

1. `POST /account/mcp-instances/create` → `InstancesController::createAction()`
2. `McpInstancesDomainService::createMcpInstance(string $accountCoreId)`
   - Ensures no existing instance for the account.
   - Generates `vncPassword` and `mcpBearer` and persists the entity.
   - After flush, derives `instanceSlug`, `containerName`, and subdomains.
3. Docker lifecycle: `DockerManagementFacade::createAndStartContainer()`
   - `ContainerManagementDomainService` builds a `docker run` with env vars and Traefik labels; then starts the container.
4. Container state is set to `running` upon success.

### Stop & remove (user-driven)

1. `POST /account/mcp-instances/stop` → `InstancesController::stopAction()`
2. `DockerManagementFacade::stopAndRemoveContainer()` and entity deletion.

### Restart (user-driven)

1. `POST /account/mcp-instances/restart-processes` with `instanceId` (ownership validated).
2. `DockerManagementFacade::restartContainer()` updates `containerState` accordingly.

## Container design (inside per-instance image)

- Processes: Xvfb, Playwright MCP (Chromium), x11vnc, noVNC/websockify (supervised).
- Fixed internal ports: MCP 8080, VNC 5900 (internal), noVNC 6080.
- Env vars: `INSTANCE_ID`, `SCREEN_WIDTH`, `SCREEN_HEIGHT`, `COLOR_DEPTH`, `VNC_PASSWORD`.

## Routing (Traefik via labels)

- Per-instance subdomains:
  - `mcp-<slug>.mcp-as-a-service.com` → container:8080
  - `vnc-<slug>.mcp-as-a-service.com` → container:6080
- MCP router attaches ForwardAuth middleware:
  - Address: `https://app.mcp-as-a-service.com/auth/mcp-bearer-check`
  - Request carries original `Authorization: Bearer <token>` and a stable `X-MCP-Instance` header set by a companion middleware.

## ForwardAuth endpoint contract

- Route: `GET /auth/mcp-bearer-check`
- Extract instance from Host header (`mcp-<slug>.…`) or `X-MCP-Instance`.
- Compare Bearer token to `McpInstance.mcpBearer` and return 204 on success, 401/403 otherwise.
- 5‑minute in-memory cache recommended (Symfony cache); failures are not cached.

## Health and status

- `ContainerManagementDomainService` considers the container healthy if MCP (8080) and noVNC (6080) respond inside the container via `docker exec curl`.
- `DockerManagementFacade::getContainerStatus()` returns `ContainerStatusDto` with `mcpEndpoint` and `vncEndpoint` URLs derived from subdomains.

## Web UI surfaces

### User (`/account/mcp-instances`)

- Shows: VNC password, MCP Bearer, subdomain URLs, health, and actions to create/stop.
- Live health component polls every 2s for status.

## CLI and privileged access

- The Symfony app calls Docker via a wrapper: `bin/docker-cli-wrapper.sh` (executed with `sudo -n` via a sudoers entry).
- Allowed subcommands are restricted; container names must match `^mcp-instance-[a-zA-Z0-9]+$` and the image must be `maas-mcp-instance`.
- For troubleshooting as `www-data`:
  - `sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh ps`

## Deployment notes (production)

- Traefik container listens on 80/443 and uses host-mounted wildcard certificates from `/etc/letsencrypt/live/mcp-as-a-service.com/`.
- Host nginx listens on 8090 only.
- Traefik reaches host nginx via `host.docker.internal:8090`.

## Quick reference

- Data model: `McpInstance` with per-instance secrets and derived routing fields.
- Routing: Traefik via Docker labels; TLS at the edge; MCP protected by ForwardAuth.
- Health: MCP and noVNC HTTP checks via `docker exec`.

