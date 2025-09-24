#!/bin/bash
set -e

echo "Building Metabase MCP Server Docker image..."

docker build -t maas-mcp-instance-metabase-v1 .

echo "Build completed successfully!"

echo "Running health check..."
docker run --rm --name metabase-mcp-test -d \
  -e SCREEN_WIDTH=1024 \
  -e SCREEN_HEIGHT=768 \
  -e COLOR_DEPTH=24 \
  -e VNC_PASSWORD=testpass \
  -e METABASE_URL=http://example.com \
  -e METABASE_API_KEY=test-key \
  maas-mcp-instance-metabase-v1

sleep 10

if docker ps | grep -q metabase-mcp-test; then
  echo "Health check passed - container is running"
  docker stop metabase-mcp-test
else
  echo "Health check failed - container not running"
  docker logs metabase-mcp-test || true
  docker stop metabase-mcp-test || true
  exit 1
fi

echo "Metabase MCP Server image is ready!"
