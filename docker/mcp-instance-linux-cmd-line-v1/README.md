# MCP Instance Docker Image — Linux Cmd-Line v1

This Docker image packages the Linux command-line MCP server with a minimal runtime and supervisord.

## Components

- **Node.js MCP Server (linux cmd-line)**: Node-based MCP server
- **supervisord**: Process supervisor to manage the server
 - **sudo (passwordless for mcp)**: Allows the `mcp` user to gain root privileges

## Source

- Upstream MCP server: https://github.com/dx-tooling/maas-mcpserver-linux-cmd-line

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `INSTANCE_ID` | "" | Unique identifier for the instance |
| `MCP_PORT` | 8080 | MCP server port |
| `INSTANCE_TYPE` | linux-cmd-line-v1 | Instance type hint |

## Exposed Ports

- `8080`: MCP protocol endpoint (`/mcp` + `/messages` for POST)

## Health Checks

The container includes a health check that considers HTTP status < 500 from `/mcp` as healthy.

## Usage

### Basic Usage
```bash
docker run -d \
  -e INSTANCE_ID=my-instance \
  maas-mcp-instance-linux-cmd-line-v1:latest
```

### For Local Testing
```bash
docker run -d \
  -p 8080:8080 \
  -e INSTANCE_ID=test \
  maas-mcp-instance-linux-cmd-line-v1:latest
```

Then access:
- MCP endpoint: http://localhost:8080/mcp

## Sudo Capability

The container grants passwordless sudo to the `mcp` user for administrative operations initiated by the MCP server or tooling.

- Configuration: `/etc/sudoers.d/mcp` contains `mcp ALL=(ALL) NOPASSWD:ALL`.
- Quick check:
```bash
docker exec -u mcp mcp-test sh -lc 'sudo -n id -u'  # outputs 0
```

## Building

Run the build script:
```bash
./build.sh
```

## Process Management

The container uses supervisord to manage the MCP server:
- Auto-restarts on failure
- Logs are available in `/home/mcp/logs/`

## Resource Limits

Recommended resource limits:
- Memory: 256MB+
- CPU: No specific limit needed


