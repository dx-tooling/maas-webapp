# MCP Instance Container
# Contains: Xvfb, Playwright MCP, x11vnc, websockify/noVNC
FROM node:22-bookworm

# Install system dependencies
# We do not really use tigervnc-standalone-server itself, but it contains vncpasswd
RUN apt-get update && apt-get install -y \
    xvfb \
    x11vnc \
    tigervnc-standalone-server \
    websockify \
    supervisor \
    curl \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Install noVNC
RUN mkdir -p /usr/share/novnc \
    && wget -qO- https://github.com/novnc/noVNC/archive/v1.4.0.tar.gz \
    | tar xz --strip-components=1 -C /usr/share/novnc

# Create application user
RUN useradd -m -s /bin/bash mcp \
    && mkdir -p /home/mcp/logs

# Install Playwright and dependencies
USER root
RUN npm install -g @playwright/mcp@latest
RUN npx playwright install-deps

USER mcp
WORKDIR /home/mcp
RUN npm init -y \
    && npm install @playwright/mcp@latest \
    && npx playwright install chromium

# Copy supervisor configuration
USER root
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /home/mcp/vnc \
    && chown -R mcp:mcp /home/mcp

# Environment variables (defaults)
ENV DISPLAY=:99
ENV SCREEN_WIDTH=1280
ENV SCREEN_HEIGHT=720
ENV COLOR_DEPTH=24
ENV MCP_PORT=8080
ENV VNC_PORT=5900
ENV NOVNC_PORT=6080
ENV INSTANCE_ID=""
ENV VNC_PASSWORD=""

# Expose ports
EXPOSE 8080 5900 6080

# Health checks
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:6080/mcp || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
