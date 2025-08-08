# MCP Instance Docker Image

This Docker image contains a complete Playwright MCP environment with VNC access.

## Components

- **Xvfb**: Virtual X11 display server
- **Playwright MCP**: Node.js MCP server with Chromium browser
- **x11vnc**: VNC server for remote desktop access
- **websockify/noVNC**: Web-based VNC client

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `INSTANCE_ID` | "" | Unique identifier for the instance |
| `VNC_PASSWORD` | "" | Password for VNC access |
| `SCREEN_WIDTH` | 1280 | Virtual display width |
| `SCREEN_HEIGHT` | 720 | Virtual display height |
| `COLOR_DEPTH` | 24 | Color depth |
| `MCP_PORT` | 8080 | MCP server port |
| `VNC_PORT` | 5900 | VNC server port |
| `NOVNC_PORT` | 6080 | noVNC web client port |

## Exposed Ports

- `8080`: MCP protocol endpoints (`/mcp`, `/sse`)
- `5900`: Raw VNC (internal use only)
- `6080`: noVNC web client

## Health Checks

The container includes health checks for:
- MCP endpoint availability
- noVNC web interface

## Usage

### Basic Usage
```bash
docker run -d \
  -e INSTANCE_ID=my-instance \
  -e VNC_PASSWORD=mypassword \
  maas-mcp-instance:latest
```

### With Custom Display Settings
```bash
docker run -d \
  -e INSTANCE_ID=my-instance \
  -e VNC_PASSWORD=mypassword \
  -e SCREEN_WIDTH=1920 \
  -e SCREEN_HEIGHT=1080 \
  maas-mcp-instance:latest
```

### For Local Testing
```bash
docker run -d \
  -p 8080:8080 \
  -p 6080:6080 \
  -e INSTANCE_ID=test \
  -e VNC_PASSWORD=test \
  maas-mcp-instance:latest
```

Then access:
- MCP endpoints: http://localhost:8080/mcp
- noVNC client: http://localhost:6080

## Building

Run the build script:
```bash
./build.sh
```

## Process Management

The container uses supervisord to manage all processes:
- Start order: Xvfb → Playwright MCP → x11vnc → websockify
- All processes auto-restart on failure
- Logs are available in `/home/mcp/logs/`

## Resource Limits

Recommended resource limits:
- Memory: 1GB
- CPU: No specific limit needed
