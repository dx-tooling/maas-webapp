## Docker-based rewrite of MCP/VNC orchestration and proxying

This change request proposes replacing the current OS-process- and port-based orchestration with a Docker-centric, subdomain-based solution. Each MCP/VNC instance will run inside its own container and be exposed via per-instance subdomains, removing dynamic host port management.

### Goals

- Replace host-managed processes (Xvfb, Playwright MCP, x11vnc, websockify) with a single per-instance Docker container.
- Expose MCP and VNC per instance at stable subdomains instead of dynamic host ports.
- Keep security parity or better: bearer token for MCP, password for VNC; support TLS.
- Simplify lifecycle: start/stop/restart via Docker; reduce fragile process-grep logic.
- Minimize app code changes at the UI level; keep flows and semantics similar.
- Use Traefik as the HTTP entrypoint for MCP/VNC instances (dynamic discovery via labels, ACME TLS, subdomain routing, middlewares).

### Non-goals

- Switching away from nginx for the main webapp. Traefik will be introduced for MCP/VNC instance routing alongside the existing nginx for the webapp.

### Implementation progress tracker

- Last updated: 2025-08-13

#### Completed
- [x] Docker image for MCP instance with Xvfb, Playwright MCP, x11vnc, noVNC; supervisor-managed; environment-controlled
- [x] Local Traefik setup in docker-compose with example instance and routing on `*.localhost`
- [x] Traefik labels generation in app for per-instance MCP/VNC + ForwardAuth attachment
- [x] Entity/model updates: `McpInstance` fields (`instanceSlug`, `containerName`, `mcpBearer`, `mcpSubdomain`, `vncSubdomain`, `containerState`)
- [x] Docker lifecycle services: create/start/stop/restart/remove, plus endpoint-based health checks
- [x] ForwardAuth endpoint (`/auth/mcp-bearer-check`) with 5‑minute token cache and logging
- [x] UI updates: subdomain-based URLs, process/health display on instance dashboard
- [x] Database migrations for new fields
- [x] CLI commands: add Docker CLI wrapper for the Symfony app, with sudo-based privilege management (see bin/docker-cli-wrapper.sh and docs/infrastructure/etc/sudoers.d/101-www-data-docker-cli-wrapper)
- [x] DNS: add wildcard `*.mcp-as-a-service.com` pointing to the main IP
- [x] Production Traefik deployment on 80/443, route `app.*` → nginx:8090, use wildcard TLS
- [x] Docker network `mcp_instances` on prod
- [x] nginx reconfiguration on prod host to listen on 8090 and drop TLS (Traefik terminates)

#### In progress / Next
- [ ] Replace `shell_exec` and fragile exit-code handling with Symfony Process for Docker commands
- [ ] Ownership linkage: ensure `accountCoreId` uses Account UUID (not email); fix lookups and DTO usage accordingly
- [ ] Align template/DTO property names (e.g., use `vncPassword` instead of `password` in templates)
- [ ] Container health: consider adding a noVNC healthcheck or document reliance on application-level checks
- [ ] Admin UX: list containers with status/uptime, and per-container actions
- [ ] Verify traefik<->webapp ForwardAuth integration
- [ ] Tests: ForwardAuth functional test; domain/facade lifecycle tests; UI smoke test for dashboard
- [ ] Rate-limit failed ForwardAuth attempts; add minimal metrics/logging
- [ ] Documentation: update prodsetupbook/runbook and troubleshooting sections

#### Nice-to-have (later)
- [ ] Show resource usage (docker stats) on admin dashboard
- [ ] Attach security headers middleware chains to Traefik routers
- [ ] Basic observability/alerts for unhealthy containers

## Architecture overview

- One Docker container per user instance encapsulating:
  - Xvfb (virtual display)
  - Playwright MCP server (Node)
  - x11vnc (VNC server)
  - noVNC/websockify (browser-based VNC client)
- Networking:
  - Internal container ports are fixed (e.g., 8080 MCP, 5900 VNC, 6080 noVNC), no dynamic host port mapping.
  - Traefik proxies to containers by name over a shared Docker network using Docker provider labels.
- Routing:
  - Per-instance subdomains for MCP and VNC managed by Traefik routers.
  - Bearer protection for MCP enforced at the edge via Traefik ForwardAuth middleware; VNC password remains enforced by x11vnc.
  - Raw VNC 5900 remains internal-only (no external exposure); only noVNC at 6080 is proxied by Traefik.

### Subdomain scheme and TLS

- Chosen scheme: `mcp-<instance-id>.mcp-as-a-service.com` and `vnc-<instance-id>.mcp-as-a-service.com`.
- TLS: covered by a single wildcard `*.mcp-as-a-service.com` via Traefik. The certificate already exists, its renewal is handled by a process that is out-of-scope for this change request. The certificate files are available on the filesystem of the Docker host system.

## Container design

- Base image: Debian/Ubuntu (or an official Node image) plus packages: `xvfb`, `x11vnc`, `novnc`, `websockify`, `chromium` (or Playwright's Chromium deps), `nvm` not required inside container (use Node image).
- Entrypoint: supervisord to manage the 4 processes and ensure ordered start/stop.
- Environment variables per container:
  - `INSTANCE_ID` (slug/uuid)
  - `SCREEN_WIDTH`, `SCREEN_HEIGHT`, `COLOR_DEPTH`
  - `VNC_PASSWORD`
  - Fixed ports exposed: `MCP_PORT=8080`, `VNC_PORT=5900`, `NOVNC_PORT=6080`
- Healthchecks:
  - MCP HTTP check (`/mcp` or `/sse` availability)
  - noVNC HTTP check on 6080
- Resource limits and policies:
  - `--memory=1g` per container
  - `--restart always`

### Process commands inside the container

- Xvfb: `Xvfb :99 -screen 0 ${SCREEN_WIDTH}x${SCREEN_HEIGHT}x${COLOR_DEPTH}`
- Playwright MCP (Node): `npx @playwright/mcp@latest --port 8080 --no-sandbox --isolated --browser chromium --host 0.0.0.0` with `DISPLAY=:99`
- x11vnc: generate password file; run `x11vnc -display :99 -forever -shared -rfbport 5900 -rfbauth /run/secrets/vnc.pwd`
- noVNC/websockify: `websockify --web=/usr/share/novnc/ 6080 localhost:5900`

## Networking and proxying (Traefik)

### Primary reverse proxy architecture
- **Traefik replaces nginx as the primary HTTP entrypoint** on ports 80/443.
- nginx is reconfigured to serve the native webapp on an internal port (e.g., 8080) and is no longer directly exposed to the internet.
- Run Traefik as a container on the same host as the native webapp.
- **Important**: The main Symfony webapp runs natively (nginx + PHP-FPM), NOT in a container. Only MCP instances run in containers.

### DNS requirements
- Existing DNS: `app.mcp-as-a-service.com` → static IPv4 (unchanged)
- **New DNS**: `*.mcp-as-a-service.com` → same static IPv4 (wildcard record)

### Traefik routing configuration
- Configure Traefik Docker provider to watch labels and create routers/services/middlewares automatically.
- **Main webapp routing**: Traefik routes `app.mcp-as-a-service.com` → native nginx on host port 8080
- **Per-instance routing** via labels set on the instance container:
  - MCP router: Host rule `mcp-<instance>.mcp-as-a-service.com`; service targets container port 8080.
  - MCP auth: ForwardAuth middleware pointing to a Symfony endpoint that validates the `Authorization: Bearer` header against the instance secret; attach middleware to MCP router.
  - VNC router: Host rule `vnc-<instance>.mcp-as-a-service.com`; service targets container port 6080 (noVNC). The raw VNC 5900 remains internal.

### TLS and certificates
- Use existing wildcard certificate process (out of scope).
- Certificate files are available on the filesystem of the Docker host system.
- Traefik handles TLS termination for all domains (`app.*` and `mcp-*/vnc-*` subdomains).

## Application changes (Symfony)

### Data model

- `McpInstance` changes:
  - Add `instanceSlug` (DNS-safe); use existing UUID with hyphens stripped.
  - Add `containerName`, `containerState` (Running/Stopped/Error).
  - Remove dynamic host ports: keep screen settings, keep `vncPassword`.
  - Add dedicated `mcpBearer` secret (auto-generated on creation, static-per-instance).
  - Store derived subdomains: `mcpSubdomain`, `vncSubdomain` for display convenience.

### Facade/domain services

 - Replace OS-process launch/stop/restart with Docker operations (via Docker CLI):
  - Create container: `docker run` with env vars, resource limits, labels identifying the instance.
  - Start/stop/restart: `docker start/stop/restart` (CLI invocations).
  - Inspect status: `docker inspect` for health/status; remove `ps aux` regex parsing.
  - Containers can simply be restarted as they have no relevant state.
 - Replace nginx config generation entirely with Docker labels for Traefik (no templated file generation or reloads needed).
 - Implement a small ForwardAuth endpoint in Symfony (e.g., `/auth/mcp-bearer-check`) that:
   - Receives original request headers from Traefik.
   - Extracts instance identifier from SNI/Host header or from a Traefik-injected header.
   - Validates the bearer token against DB (prefer `mcpBearer` separate from `vncPassword`).
   - Returns 2xx to allow, 401/403 to deny.

### CLI commands

- Update `launch/stop` commands to call Docker operations instead of OS processes.
- Add a `recreate` command (stop → remove → create → start) for clean restarts.

### UI changes

- User dashboard:
  - Replace port-based URLs with subdomains: show `https://mcp-<instance>.mcp-as-a-service.com/sse` and `https://vnc-<instance>.mcp-as-a-service.com`.
  - Health indicators from Docker inspect/healthchecks.
- Admin dashboard:
  - List containers with status, uptime, resource usage (from Docker stats if desired).
  - Per-container actions: start/stop/restart/recreate.

## Traefik configuration via labels

- Instance container labels (illustrative):
  - MCP
    - `traefik.enable=true`
    - `traefik.http.routers.mcp-<instance>.rule=Host(` + "mcp-<instance>.mcp-as-a-service.com" + `)`
    - `traefik.http.routers.mcp-<instance>.entrypoints=websecure`
    - `traefik.http.routers.mcp-<instance>.tls.certresolver=letsencrypt`
    - `traefik.http.services.mcp-<instance>.loadbalancer.server.port=8080`
    - `traefik.http.middlewares.mcp-<instance>-auth.forwardauth.address=https://app.mcp-as-a-service.com/auth/mcp-bearer-check`
    - `traefik.http.routers.mcp-<instance>.middlewares=mcp-<instance>-auth`
  - VNC
    - `traefik.http.routers.vnc-<instance>.rule=Host(` + "vnc-<instance>.mcp-as-a-service.com" + `)`
    - `traefik.http.routers.vnc-<instance>.entrypoints=websecure`
    - `traefik.http.routers.vnc-<instance>.tls.certresolver=letsencrypt`
    - `traefik.http.services.vnc-<instance>.loadbalancer.server.port=6080`
  - Optionally add `securityHeaders` middleware chains if desired.

## ForwardAuth contract (MCP bearer validation)

Purpose: Edge-enforce Authorization: Bearer for MCP requests using Traefik ForwardAuth calling a Symfony endpoint.

ForwardAuth middleware configuration (illustrative):

- Address: `https://app.mcp-as-a-service.com/auth/mcp-bearer-check`
- Trust headers: enable `trustForwardHeader=true` (Traefik) if needed.
- Response headers to forward upstream: set `authResponseHeaders=X-MCP-Instance-Id` (optional convenience).

Request expectations (from Traefik to Symfony):

- Method: `GET` (or `HEAD`) is sufficient; no body required.
- Headers provided by Traefik:
  - `Host`: e.g., `mcp-<instance-id>.mcp-as-a-service.com`
  - `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-For`
  - `Authorization`: `Bearer <token>`
  - Optional: any additional headers configured in Traefik router/middleware

Instance identification:

- Parse the `Host` header. Expected patterns:
  - MCP: `mcp-<instance-id>.mcp-as-a-service.com`
  - VNC is not ForwardAuth-protected (handled by VNC password), so the middleware only attaches to MCP routers.
- Extract `<instance-id>` as the substring between `mcp-` and the first `.`
- Map `<instance-id>` to DB:
  - Use `McpInstance.id` (preferred) or `instanceSlug` if introduced; ensure it is DNS-safe and matches the subdomain segment.

Authorization logic:

- Extract token from `Authorization` header (case-insensitive, `Bearer <token>`).
- Lookup instance by id/slug; fetch its secret:
  - Recommended: dedicated `mcpBearer` (separate from `vncPassword`).
  - Transitional: allow fallback to `vncPassword` if `mcpBearer` is null.
- Constant-time compare token to stored secret.

Responses to Traefik:

- Allow: `204 No Content` (or `200 OK`) without body; include `X-MCP-Instance-Id: <id>` if you want it forwarded upstream.
- Deny (missing/invalid token): `401 Unauthorized` with `WWW-Authenticate: Bearer realm="MCP"` (optional), or `403 Forbidden`.
- Error: `5xx` for unexpected server errors (Traefik will treat as deny).

Symfony controller outline (minimal):

```text
Route: GET /auth/mcp-bearer-check
Steps:
1) Read Host and Authorization headers
2) Parse instanceId from Host (strip "mcp-" prefix, take label before first dot)
3) Find McpInstance by id/slug
4) Extract expected secret (mcpBearer || vncPassword)
5) Compare to bearer from header; return 204 or 401
```

Possible response headers (optional convenience):

- `X-MCP-Instance-Id`: the resolved instance id
- You can configure Traefik middleware: `forwardauth.authResponseHeaders=X-MCP-Instance-Id`

Operational notes:

- Keep the endpoint stateless and fast (<10ms typical); add a 5-minute TTL in-memory cache (Symfony cache) keyed by instanceId since bearer tokens are static.
- Rate-limit failures per source IP to mitigate brute force.
- Log only high-level events; never log full tokens.

### Appendix: Symfony ForwardAuth controller (pseudo-code)

```php
<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// GET /auth/mcp-bearer-check
function mcpBearerCheckAction(Request $request): Response {
    $host = (string) $request->headers->get('host', '');
    $auth = (string) $request->headers->get('authorization', '');

    // Require Bearer header
    if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
        return new Response('', 401, ['WWW-Authenticate' => 'Bearer realm="MCP"']);
    }
    $presented = $m[1];

    // Extract instance id from host: mcp-<instance-id>.mcp-as-a-service.com
    $instanceId = null;
    if (preg_match('/^mcp-([^.]+)\./i', $host, $mm)) {
        $instanceId = $mm[1];
    }
    if ($instanceId === null) {
        return new Response('', 403);
    }

    // Lookup instance (by id or slug)
    // $instance = $repo->find($instanceId) ?? $repo->findOneBy(['instanceSlug' => $instanceId]);
    $instance = null; // placeholder
    if ($instance === null) {
        return new Response('', 403);
    }

    // Derive expected secret (use dedicated bearer)
    $expected = $instance->getMcpBearer();
    if ($expected === null || $expected === '') {
        return new Response('', 403);
    }

    // Constant-time compare
    if (!hash_equals($expected, $presented)) {
        return new Response('', 401, ['WWW-Authenticate' => 'Bearer realm="MCP"']);
    }

    // Optionally expose instance id to upstream
    return new Response('', 204, ['X-MCP-Instance-Id' => $instanceId]);
}
```

## Security model

- Keep bearer token (edge-enforced) for MCP via Traefik ForwardAuth. Store a dedicated `mcpBearer` separate from `vncPassword`.
- Keep VNC password inside the container; noVNC simply transports to VNC server.
- No specific container security measures needed for v1.

## Migration plan

This is a full-replace rewrite that ignores existing state. No migration of existing instances is required.

### Development phase (completed)
1) ✅ Prepare Docker image and validate locally with one instance.
2) ✅ Prepare Traefik configuration with Docker Compose.
3) ✅ Add new DB fields (slug, containerName, bearer, subdomains).
4) ✅ Implement new Docker-based facade replacing OS process management.
5) ✅ Update UI to use subdomain-based URLs.

### Development vs Production environments
- **Development (docker-compose.yml)**: All components containerized for consistency and isolation
  - Symfony webapp: Docker container
  - Database: Docker Postgres container  
  - Traefik: Docker container
  - MCP instances: Docker containers
- **Production**: Hybrid native + container approach for performance and existing infrastructure
  - Symfony webapp: Native nginx + PHP-FPM installation
  - Database: Native MariaDB (existing)
  - Traefik: Docker container (reverse proxy only)
  - MCP instances: Docker containers (managed by native webapp)

### Production deployment tasks
1) **DNS setup**: Add wildcard DNS record `*.mcp-as-a-service.com` → same static IPv4 as `app.mcp-as-a-service.com`
2) **nginx reconfiguration**: 
   - Move nginx from ports 80/443 to internal port 8080
   - Remove SSL/TLS configuration from nginx (Traefik will handle TLS termination)
   - Simplify nginx to basic HTTP application server
3) **Traefik deployment**:
   - Deploy Traefik container on ports 80/443
   - Configure TLS certificates (existing wildcard cert process)
   - Add webapp routing: `app.mcp-as-a-service.com` → nginx:8080
4) **Application deployment**: Deploy updated Symfony application with Docker management
5) **Verification**: Test main webapp access and create/test one MCP instance

## Production infrastructure setup

### Network topology
```
Internet (80/443) → Traefik Container → {
  app.mcp-as-a-service.com → nginx:8080 (native Symfony webapp on host)
  mcp-{slug}.mcp-as-a-service.com → mcp-instance-container:8080
  vnc-{slug}.mcp-as-a-service.com → mcp-instance-container:6080
}
```

### Production component architecture
- **Host system (Ubuntu)**: Native nginx + PHP-FPM + Symfony webapp, native MariaDB, Docker daemon
- **Traefik container**: Reverse proxy handling all HTTP traffic (ports 80/443)
- **MCP instance containers**: Individual isolated browser environments (Docker managed by native webapp)

### Required production changes
1. **DNS**: Add `*.mcp-as-a-service.com` CNAME or A record → same IP as `app.mcp-as-a-service.com`
2. **nginx**: Reconfigure native nginx to listen on internal port 8080 instead of 80/443
3. **TLS**: Traefik takes over SSL/TLS termination using existing wildcard certificate
4. **Database**: Update Symfony config to use native MariaDB instead of Docker Postgres
5. **Docker network**: Create shared Docker network for Traefik and MCP containers: `docker network create mcp_instances`
6. **Permissions**: Ensure native webapp (www-data user) has Docker daemon access: `usermod -aG docker www-data`
7. **Traefik host access**: Configure Traefik container to access host nginx on port 8080 (use `host.docker.internal` or `--network host`)

### Deployment strategy
- **Zero-downtime**: Not required for this project (pre-production)
- **Rollback**: Keep existing nginx config as backup; can revert by stopping Traefik and reverting nginx to ports 80/443

## Operational considerations

- Logging: docker logs are not relevant in v1.
- Monitoring: container health checks only need to verify HTTP endpoints are available.
- Backups: no persistent volume needed; DB remains the source of truth.
- Cost/perf: per-instance container overhead with 1 GiB memory limit.

## Deliverables

- Docker image and compose template for local dev with working Traefik instance.
- New Symfony facade/services for Docker lifecycle management.
- Database migration and entity updates.
- Updated admin/user UIs.
- Documentation updates (funcbook, prodsetupbook, runbook).

## Acceptance criteria

- Creating an instance starts a container; MCP and noVNC are available at the documented subdomains secured per design.
- Health status is derived from Docker and reflects reality.
- Stop/restart/recreate from UI and CLI work reliably.
- Traefik routes subdomains to the correct containers; TLS certificates are available from existing process; no dynamic host ports exist.
