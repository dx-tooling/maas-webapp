#!/bin/bash
# Build script for MCP Instance Docker image (Linux Cmd-Line)

set -e

echo "Building MCP Instance Docker image (linux-cmd-line-v1)..."

# Build the image (context is this directory)
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

# Use /health which is the health check endpoint and must return 200 for healthy status
HEALTH_ENDPOINT_RESPONSE_CODE=$(docker exec mcp-test sh -lc 'curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/health' || echo 0)
if [ "$HEALTH_ENDPOINT_RESPONSE_CODE" -eq "200" ]; then
  echo "✅ Health endpoint is healthy (code: $HEALTH_ENDPOINT_RESPONSE_CODE)"
else
  echo "❌ Health endpoint is not healthy (code: $HEALTH_ENDPOINT_RESPONSE_CODE)"
  docker logs mcp-test || true
  docker stop mcp-test || true
  exit 1
fi

# Verify sudo access for mcp user
echo "Verifying mcp user sudo access..."
SUDO_CHECK=$(docker exec -u mcp mcp-test sh -lc 'sudo -n id -u' || echo "")
if [ "$SUDO_CHECK" = "0" ]; then
  echo "✅ mcp can sudo to root (uid 0)"
else
  echo "❌ mcp cannot sudo to root"
  docker exec -u mcp mcp-test sh -lc 'id && sudo -n id -u || true'
  docker logs mcp-test || true
  docker stop mcp-test || true
  exit 1
fi

# Cleanup
docker stop mcp-test

echo "✅ MCP Instance image built and tested successfully!"
echo "Usage: docker run -e INSTANCE_ID=myid maas-mcp-instance-linux-cmd-line-v1:latest"
