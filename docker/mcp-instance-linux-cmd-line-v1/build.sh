#!/bin/bash
# Build script for MCP Instance Docker image (Linux Cmd-Line)

set -e

echo "Building MCP Instance Docker image (linux-cmd-line-v1)..."

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
docker build -t maas-mcp-instance-linux-cmd-line-v1:latest "$BUILD_DIR"

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

# Cleanup
docker stop mcp-test

echo "✅ MCP Instance image built and tested successfully!"
echo "Usage: docker run -e INSTANCE_ID=myid maas-mcp-instance-linux-cmd-line-v1:latest"
