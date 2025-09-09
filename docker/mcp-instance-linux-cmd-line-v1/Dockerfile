# MCP Instance Container - Linux Cmd-Line MCP Server
# Contains: Node.js runtime, MCP server (linux cmd-line), supervisord
FROM node:22-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    supervisor \
    curl \
    ca-certificates \
    git \
    && rm -rf /var/lib/apt/lists/*

# Create application user
RUN useradd -m -s /bin/bash mcp \
    && mkdir -p /home/mcp/logs \
    && chown -R mcp:mcp /home/mcp

# Install the Linux Cmd-Line MCP server from GitHub
USER mcp
WORKDIR /home/mcp
RUN npm init -y \
    && npm install https://github.com/dx-tooling/maas-mcpserver-linux-cmd-line.git

# Copy supervisor configuration
USER root
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor \
    && chown -R mcp:mcp /home/mcp

# Environment variables (defaults)
ENV MCP_PORT=8080 \
    INSTANCE_ID="" \
    INSTANCE_TYPE="linux-cmd-line-v1"

# Expose ports
EXPOSE 8080

# Health checks (treat < 500 as healthy like app logic)
# Use /mcp which is the SSE session initiator endpoint and returns < 500 on availability
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -s -o /dev/null -w "%{http_code}" http://localhost:${MCP_PORT}/mcp | grep -E "^(0|[1-4][0-9]{2})$" >/dev/null || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]


