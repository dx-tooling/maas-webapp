# Multi-stage build for Metabase MCP Server
FROM node:20-slim AS builder

# Install git for npm dependencies that might need it
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

# Create app directory
WORKDIR /app

# Clone and build the metabase-mcp-server
RUN git clone https://github.com/CognitionAI/metabase-mcp-server.git .
RUN npm install
RUN npm run build

# Production stage
FROM node:20-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    supervisor \
    xvfb \
    x11vnc \
    fluxbox \
    novnc \
    websockify \
    curl \
    procps \
    net-tools \
    && rm -rf /var/lib/apt/lists/*

# Create app directory
WORKDIR /app

# Copy built application from builder stage
COPY --from=builder /app .

# Copy supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor /tmp/.X11-unix
RUN chmod 1777 /tmp/.X11-unix

# Expose ports
EXPOSE 8080 6080

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
