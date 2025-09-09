#!/bin/bash
# Build script for MCP Instance Docker image (Linux Cmd-Line)

set -e

echo "Building MCP Instance Docker image (linux-cmd-line-v1)..."

# Build the image
docker build -t maas-mcp-instance-linux-cmd-line-v1:latest .

echo "Testing the image..."

# Test basic functionality
docker run --rm -d \
  --name mcp-test \
  -e INSTANCE_ID=test123 \
  maas-mcp-instance-linux-cmd-line-v1:latest

# Wait for services to start
echo "Waiting for services to start..."
sleep 6

# Check health (consider HTTP < 500 as healthy like the app logic) — probe /mcp
MCP_CODE=$(docker exec mcp-test sh -lc 'curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/mcp' || echo 0)
if [ "$MCP_CODE" -gt 0 ] && [ "$MCP_CODE" -lt 500 ]; then
  echo "✅ MCP endpoint is healthy (code: $MCP_CODE)"
else
  echo "❌ MCP endpoint failed (code: $MCP_CODE)"
  docker logs mcp-test || true
  docker stop mcp-test || true
  exit 1
fi

# Cleanup
docker stop mcp-test

echo "✅ MCP Instance image built and tested successfully!"
echo "Usage: docker run -e INSTANCE_ID=myid maas-mcp-instance-linux-cmd-line-v1:latest"


