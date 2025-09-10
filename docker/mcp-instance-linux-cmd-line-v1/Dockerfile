# MCP Instance Container - Linux Cmd-Line MCP Server
# Contains: Node.js runtime, MCP server (linux cmd line), Xvfb + x11vnc + noVNC, supervisord
FROM node:22-bookworm

# Install system dependencies (noVNC stack and utilities)
RUN apt-get update && apt-get install -y \
    xvfb \
    x11vnc \
    tigervnc-standalone-server \
    websockify \
    supervisor \
    curl \
    ca-certificates \
    git \
    wget \
    xterm \
    && rm -rf /var/lib/apt/lists/*

# Install noVNC
RUN mkdir -p /usr/share/novnc \
    && wget -qO- https://github.com/novnc/noVNC/archive/v1.4.0.tar.gz \
    | tar xz --strip-components=1 -C /usr/share/novnc

# Create application user
RUN useradd -m -s /bin/bash mcp \
    && mkdir -p /home/mcp/logs \
    && mkdir -p /home/mcp/vnc \
    && chown -R mcp:mcp /home/mcp

# Install the Linux Cmd-Line MCP server from GitHub (cache-busted)
USER mcp
WORKDIR /home/mcp
ADD "https://api.github.com/repos/dx-tooling/maas-mcpserver-linux-cmd-line/commits?per_page=1" skipcache
RUN npm init -y \
    && npm install "git://github.com/dx-tooling/maas-mcpserver-linux-cmd-line.git"

# Copy supervisor configuration
USER root
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor \
    && chown -R mcp:mcp /home/mcp

# Environment variables (defaults)
ENV DISPLAY=:99 \
    SCREEN_WIDTH=1280 \
    SCREEN_HEIGHT=720 \
    COLOR_DEPTH=24 \
    MCP_PORT=8080 \
    VNC_PORT=5900 \
    NOVNC_PORT=6080 \
    INSTANCE_ID="" \
    INSTANCE_TYPE="linux-cmd-line-v1" \
    VNC_PASSWORD=""

# Expose ports
EXPOSE 8080 5900 6080

# Health check via noVNC endpoint
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:${NOVNC_PORT}/vnc.html || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
