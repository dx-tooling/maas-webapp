# MCP Instance Container - Legacy Playwright Image
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
    && mkdir -p /home/mcp/logs \
    && chown -R mcp:mcp /home/mcp

# Install Playwright and dependencies
RUN apt-get update && apt-get install -y --no-install-recommends libasound2 libatk-bridge2.0-0 libatk1.0-0 libatspi2.0-0 libcairo2 libcups2 libdbus-1-3 libdrm2 libgbm1 libglib2.0-0 libnspr4 libnss3 libpango-1.0-0 libx11-6 libxcb1 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxkbcommon0 libxrandr2 libcairo-gobject2 libdbus-glib-1-2 libfontconfig1 libfreetype6 libgdk-pixbuf-2.0-0 libgtk-3-0 libharfbuzz0b libpangocairo-1.0-0 libx11-xcb1 libxcb-shm0 libxcursor1 libxi6 libxrender1 libxtst6 libsoup-3.0-0 gstreamer1.0-libav gstreamer1.0-plugins-bad gstreamer1.0-plugins-base gstreamer1.0-plugins-good libegl1 libenchant-2-2 libepoxy0 libevdev2 libgles2 libglx0 libgstreamer-gl1.0-0 libgstreamer-plugins-base1.0-0 libgstreamer1.0-0 libgtk-4-1 libgudev-1.0-0 libharfbuzz-icu0 libhyphen0 libicu72 libjpeg62-turbo liblcms2-2 libmanette-0.2-0 libnotify4 libopengl0 libopenjp2-7 libopus0 libpng16-16 libproxy1v5 libsecret-1-0 libwayland-client0 libwayland-egl1 libwayland-server0 libwebp7 libwebpdemux2 libwoff1 libxml2 libxslt1.1 libatomic1 libevent-2.1-7 libavif15 xvfb fonts-noto-color-emoji fonts-unifont xfonts-scalable fonts-liberation fonts-ipafont-gothic fonts-wqy-zenhei fonts-tlwg-loma-otf fonts-freefont-ttf

USER mcp
WORKDIR /home/mcp
RUN npm init -y \
    && npm install @playwright/mcp@latest \
    && npx playwright install chromium \
    && npx playwright install firefox

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
    CMD curl -f http://localhost:6080/vnc.html || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
