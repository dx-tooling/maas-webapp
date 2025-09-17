#!/bin/bash
# Build script for MCP Instance Docker image

set -e

echo "Building MCP Instance Docker image..."

# Create temporary build directory
BUILD_DIR=$(mktemp -d)
trap "rm -rf $BUILD_DIR" EXIT

echo "Preparing build context in $BUILD_DIR"

# Copy Dockerfile and required files to build directory
cp Dockerfile "$BUILD_DIR/"
cp supervisord.conf "$BUILD_DIR/"

# Copy registry client files
cp -r ../_mcp-instance-data-registry-clients "$BUILD_DIR/"

# Build the image from the temporary directory
echo "Building Docker image..."
docker build -t maas-mcp-instance-playwright-v1:latest "$BUILD_DIR"

echo "Testing the image..."

# Test basic functionality
docker run --rm -d \
  --name mcp-test \
  -e INSTANCE_ID=test123 \
  -e VNC_PASSWORD=testpass \
  maas-mcp-instance-playwright-v1:latest

# Wait for services to start
echo "Waiting for services to start..."
sleep 6

# Check health (consider HTTP < 500 as healthy for MCP like the app logic)
MCP_CODE=$(docker exec mcp-test sh -lc 'curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/mcp' || echo 0)
if [ "$MCP_CODE" -gt 0 ] && [ "$MCP_CODE" -lt 500 ]; then
  echo "✅ MCP endpoint is healthy (code: $MCP_CODE)"
else
  echo "❌ MCP endpoint failed (code: $MCP_CODE)"
  docker logs mcp-test
  docker stop mcp-test
  exit 1
fi

NOVNC_CODE=$(docker exec mcp-test sh -lc 'curl -s -o /dev/null -w "%{http_code}" http://localhost:6080' || echo 0)
if [ "$NOVNC_CODE" -gt 0 ] && [ "$NOVNC_CODE" -lt 500 ]; then
  echo "✅ noVNC endpoint is healthy (code: $NOVNC_CODE)"
else
  echo "❌ noVNC endpoint failed (code: $NOVNC_CODE)"
  docker logs mcp-test
  docker stop mcp-test
  exit 1
fi

# Cleanup
docker stop mcp-test

echo "✅ MCP Instance image built and tested successfully!"
echo "Usage: docker run -e INSTANCE_ID=myid -e VNC_PASSWORD=mypass maas-mcp-instance-playwright-v1:latest"
