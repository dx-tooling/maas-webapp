## Functionality Book

> **⚠️ OUTDATED**: This document describes the legacy OS process-based architecture. The application has been migrated to a Docker-based architecture as described in `docs/change-requests/docker-based-rewrite.md`. This document needs to be updated to reflect the new Docker + Traefik architecture.

This document describes how the Playwright MCP instance management works in this application: lifecycle, processes, HTTP proxying, security, and the Symfony implementation.

### Scope

- Focus is on MCP instance orchestration, OS processes, dynamic nginx proxies, and the user/admin surfaces and CLI that control them.
- The general web application (auth, templating) is conventional Symfony and not the main subject here.

## High-level architecture

- **Entity**: `App\McpInstances\Domain\Entity\McpInstance`
  - Persists one per account (at most) with display number, ports, screen settings, and `vncPassword`.
- **Domain service**: `App\McpInstances\Domain\Service\McpInstancesDomainService`
  - Creates/stops instances, assigns unique display/ports, calls the OS process facade.
- **OS processes facade/service**:
  - `App\OsProcessManagement\Facade\OsProcessManagementFacade` orchestrates actions and triggers nginx reconfig.
  - `App\OsProcessManagement\Domain\Service\OsProcessManagementDomainService` launches/stops/restarts Xvfb, Playwright MCP, x11vnc, and websockify; scrapes processes for health.
  - `App\OsProcessManagement\Domain\Service\NginxManagementDomainService` generates per-instance nginx `server {}` blocks and restarts nginx via wrapper script.
- **HTTP proxy site**: `docs/infrastructure/etc/nginx/sites-enabled/mcp-server-proxy-site.conf` includes a generated file of per-instance servers; common proxy headers/timeouts are defined here.
- **User surface**: `App\McpInstances\Presentation\Controller\InstancesController` renders `instances_dashboard.html.twig` for the signed-in user, including instance URLs, password, health, and actions.
- **Admin surface**: `App\OsProcessManagement\Presentation\Controller\OsProcessManagementController` renders `dashboard.html.twig` to inspect and control all processes across instances.

## Lifecycle of an MCP instance

### Create (user-driven)

1. `POST /account/mcp-instances/create` → `InstancesController::createAction()`
2. Calls `McpInstancesFacade::createMcpInstance(AccountCoreInfoDto)` → `McpInstancesDomainService::createMcpInstance(string $accountCoreId)`
3. Domain service:
   - Ensures no existing instance for the account.
   - Collects used ports across all instances; assigns random free numbers for `displayNumber`, `mcpPort`, `mcpProxyPort`, `vncPort`, `websocketPort` and fixed screen params.
   - Persists `McpInstance` with a generated `vncPassword`.
   - Calls `OsProcessManagementFacade::launchPlaywrightSetup(...)`.
4. Facade launches processes in order (Xvfb → MCP → VNC → websockify) via the domain service and then calls `NginxManagementDomainService::reconfigureAndRestartNginx()` to regenerate per-instance nginx configuration and restart.

### Stop & remove (user-driven)

1. `POST /account/mcp-instances/stop` → `InstancesController::stopAction()`
2. Calls `McpInstancesFacade::stopAndRemoveMcpInstance(AccountCoreInfoDto)` → `McpInstancesDomainService::stopAndRemoveMcpInstance(string)`
3. Stops processes (websockify → VNC → MCP → Xvfb) via `OsProcessManagementFacade::stopPlaywrightSetup(...)`, removes the entity, regenerates nginx config, and restarts nginx.

### Restart all processes (user-driven)

1. `POST /account/mcp-instances/restart-processes` with `instanceId` → ownership verified.
2. `McpInstancesFacade::restartProcessesForInstance(string)` delegates to `OsProcessManagementFacade::restartAllProcessesForInstance(string)` which:
   - Looks up the `McpInstance`, stops all four processes, waits briefly, then relaunches all with the persisted parameters.
   - Returns a boolean indicating overall success.

### Admin operations

- Admin dashboard shows all detected processes across types and instances. It supports per-process stop/restart and whole-instance stop/restart using the same OS management services/facade.

## Port and display allocation

- Allocation happens in `McpInstancesDomainService::createMcpInstance`:
  - `displayNumber`: random free in `[100, 2147483647]` considering all instances.
  - Ports (`mcpPort`, `mcpProxyPort`, `vncPort`, `websocketPort`): random free in `[10000, 65000]`, guaranteeing uniqueness across all instances and across all types.
  - Helper `findRandomFreeNumber(min, max, used[])` tries up to 1000 random picks; throws if no free number is found.

## OS processes launched per instance

Launched by `OsProcessManagementDomainService` (via the facade):

- Xvfb (virtual display):
  - Command: `Xvfb :<display> -screen 0 <width>x<height>x<depth> > /var/tmp/xvfb.<display>.log 2>&1 &`
- Playwright MCP (Node process):
  - Command: `/usr/bin/env bash /var/www/prod/maas-webapp/bin/launch-playwright-mcp.sh <display> <mcpPort>`
  - Script details (`bin/launch-playwright-mcp.sh`):
    - `cd /var/www/prod/mcp-env` and `source $HOME/.nvm/nvm.sh` to load Node via `nvm`.
    - Exports `DISPLAY=:<display>`.
    - Runs `nohup npx @playwright/mcp@latest` with flags:
      - `--port <mcpPort>`
      - `--no-sandbox`
      - `--isolated`
      - `--browser chromium`
      - `--host 0.0.0.0` (listens on all interfaces; still only reachable via local nginx in default setup)
    - Logs to `/var/tmp/launchPlaywrightMcp.<port>.log` and backgrounds the process.
- x11vnc (VNC server):
  - Password file: `/var/tmp/vnc.<vncPort>.pwd` via `echo "$password" | vncpasswd -f > $passwordFile`
  - Command: `x11vnc -display :<display> -forever -shared -rfbport <vncPort> -rfbauth <pwdfile> > /var/tmp/vnc.<vncPort>.log 2>&1 &`
- websockify/noVNC (HTTP → VNC):
  - Command: `websockify --web=/usr/share/novnc/ <websocketPort> localhost:<vncPort> > /var/tmp/websockify.<websocketPort>.log 2>&1 &`

Stopping processes uses `ps aux | grep ... | awk '{print $2}'` to find PIDs and `kill`/`pkill -P` to terminate trees where needed.

## Process detection and health

- Detection uses `ps aux` with regex matching per type to build DTOs:
  - `VirtualFramebufferProcessInfoDto`: matches `Xvfb :<display>` lines.
  - `PlaywrightMcpProcessInfoDto`: matches Playwright MCP command lines with `--port <port>`.
  - `VncServerProcessInfoDto`: matches `x11vnc -display :<display> ... -rfbport <port>`.
  - `VncWebsocketProcessInfoDto`: matches `websockify ... <httpPort> localhost:<vncPort>`.
- `OsProcessManagementFacade::getAllProcesses()` maps detected processes back to instance IDs using display/ports from the DB and returns arrays grouped by type.
- User dashboard computes an `allRunning` boolean for their instance (all four present) and renders green/yellow/red status.

## Dynamic nginx proxying and security

- `NginxManagementDomainService::generateNginxConfig(array<McpInstanceInfoDto>)` returns text containing one `server {}` per instance:
  - `listen <mcpProxyPort>; server_name 127.0.0.1;`
  - Enforces bearer token: if `Authorization` header does not equal `Bearer <password>`, returns `401`.
  - Proxies all paths to `http://127.0.0.1:<mcpPort>`.
- `NginxManagementDomainService::reconfigureAndRestartNginx()` runs `/var/www/prod/maas-webapp/bin/generate-mcp-proxies-wrapper.sh` which in turn:
  - Background-executes with `sudo` and logs to `/var/tmp/generate-mcp-proxies.sh.log`.
  - Calls `bin/generate-mcp-proxies.sh`, which sleeps 5s (allow processes to start) and then:
    - Runs `php bin/console --env=prod app:os-process-management:domain:generate-nginx-bearer-mappings /var/tmp/mcp-proxies.conf` as `www-data`.
    - Moves `/var/tmp/mcp-proxies.conf` to `/etc/nginx/mcp-server-proxies.conf`.
    - Validates config `nginx -t` and restarts nginx `service nginx restart`.
- `docs/infrastructure/etc/nginx/sites-enabled/mcp-server-proxy-site.conf` defines common proxy headers/timeouts and includes the dynamic file (e.g., `/etc/nginx/mcp-server-proxies.conf`).
- Result: external clients reach MCP endpoints at `http://<host>:<mcpProxyPort>/(sse|mcp)` with a required `Authorization: Bearer <password>` header.

Security notes:

- Per-instance bearer tokens are the instance `vncPassword` stored in `McpInstance` and used by nginx matching. There is no TLS on the per-instance proxy ports by default; the main app runs on HTTPS. Consider fronting high ports with TLS if needed.
- VNC auth is enforced by x11vnc using the same password, and the noVNC HTTP endpoint is unauthenticated beyond the VNC handshake.

## Web UI surfaces

### User (`/account/mcp-instances`)

- Shows the user’s instance (if present): password, proxy URLs (`/sse`, `/mcp`), VNC address, and health status. Provides actions:
  - Create instance (if none).
  - Stop & remove instance.
  - Restart all processes (validated that the `instanceId` belongs to the user).

### Admin (`/admin/os-process-management/dashboard`)

- Lists all known instances and all detected processes by type; highlights missing processes. Actions:
  - Per-process restart/stop.
  - Whole-instance stop or restart all.
  - Launch a one-off setup with arbitrary parameters (for diagnostics).

## CLI commands

- `app:os-process-management:domain:launch-playwright-setup <displayNumber> <mcpPort> <vncPort> <websocketPort> <vncPassword>`
  - Launches Xvfb, MCP, VNC, and websockify in that order. Validates distinct ports.
- `app:os-process-management:domain:stop-playwright-setup <displayNumber> <mcpPort> <vncPort> <websocketPort>`
  - Stops MCP → websockify → VNC → Xvfb.
- `app:os-process-management:domain:generate-nginx-bearer-mappings <output_file>`
  - Renders the per-instance nginx server blocks based on `McpInstanceInfoDto` data.

## Deployment and integration

- Nginx base: `docs/infrastructure/etc/nginx/nginx.conf` and app site `docs/infrastructure/etc/nginx/sites-enabled/app.mcp-as-a-service.com.conf` (main webapp on HTTPS).
- MCP proxy site: `docs/infrastructure/etc/nginx/sites-enabled/mcp-server-proxy-site.conf` includes generated per-instance servers. Ensure the target include file exists (e.g., `/etc/nginx/mcp-server-proxies.conf`).
- A sudoers entry allows `www-data` to regenerate the proxies without password (wrapper script path must match the sudoers rule). See `docs/prodsetupbook.md` for the exact command and permissions.
- Node/MCP runtime: installed under `/var/www/prod/mcp-env`; Playwright Chromium dependencies installed; `launch-playwright-mcp.sh` invoked by the OS process service.

## Error handling and logging

- Process restarts attempt to parse current command lines to recover parameters; if parsing fails, the method logs an error and returns `false`.
- Most OS process control methods log via `LoggerInterface` and return booleans rather than throwing, to keep admin actions resilient.

## Security considerations and improvements

- Current design uses plaintext high ports for MCP proxy and noVNC; consider TLS termination (SNI-based or a central reverse proxy) if clients require confidentiality.
- Password reuse between VNC and MCP bearer simplifies UX but couples auth scopes; consider separate secrets if stronger isolation is desired.
- Consider periodic password rotation with a safe regeneration flow (update DB → regenerate nginx → notify users).
- Consider non-grep process supervision (e.g., systemd units or a supervisor) for sturdier lifecycle management.

## Quick reference

- Data model: `McpInstance` (display/ports/password), 1:1 with `AccountCore`.
- Launch order: Xvfb → MCP → VNC → websockify. Stop order: reverse.
- Proxying: per-instance nginx `server { listen <mcpProxyPort>; }` with bearer enforcement; upstream is internal MCP on `mcpPort`.
- Health: presence via `ps` matching; mapped back to instances by display/ports.

