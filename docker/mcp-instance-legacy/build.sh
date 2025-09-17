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
docker build -t maas-mcp-instance:latest "$BUILD_DIR"

echo "Testing the image..."

# Test basic functionality
docker run --rm -d \
  --name mcp-test \
  -e INSTANCE_ID=test123 \
  -e VNC_PASSWORD=testpass \
  maas-mcp-instance:latest

# Wait for services to start
echo "Waiting for services to start..."
sleep 6

# Check health
if docker exec mcp-test curl -f http://localhost:8080/mcp; then
  echo "✅ MCP endpoint is healthy"
else
  echo "❌ MCP endpoint failed"
  docker logs mcp-test
  docker stop mcp-test
  exit 1
fi

if docker exec mcp-test curl -f http://localhost:6080; then
  echo "✅ noVNC endpoint is healthy"
else
  echo "❌ noVNC endpoint failed"
  docker logs mcp-test
  docker stop mcp-test
  exit 1
fi

# Cleanup
docker stop mcp-test

echo "✅ MCP Instance image built and tested successfully!"
echo "Usage: docker run -e INSTANCE_ID=myid -e VNC_PASSWORD=mypass maas-mcp-instance:latest"
