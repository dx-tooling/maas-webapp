#!/bin/bash
# Test script for MCP Instance Data Registry Client
# This script tests that the registry client is properly configured

echo "Testing MCP Instance Data Registry Client..."
echo "=========================================="
echo ""

# Check environment variables
echo "Checking environment variables:"
if [ -n "${MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT}" ]; then
    echo "✓ MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT is set: ${MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT}"
else
    echo "✗ MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT is not set"
fi

if [ -n "${MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER}" ]; then
    echo "✓ MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER is set (hidden)"
else
    echo "✗ MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER is not set"
fi

if [ -n "${MAAS_MCP_INSTANCE_UUID}" ]; then
    echo "✓ MAAS_MCP_INSTANCE_UUID is set: ${MAAS_MCP_INSTANCE_UUID}"
else
    echo "✗ MAAS_MCP_INSTANCE_UUID is not set"
fi

echo ""

# Check if registry-get command exists
if command -v registry-get &> /dev/null; then
    echo "✓ registry-get command is available"

    # Try to fetch a test key (this might fail if the key doesn't exist, which is fine)
    echo ""
    echo "Attempting to fetch 'test_key' from registry:"
    if registry-get test_key 2>/dev/null; then
        echo "✓ Successfully retrieved test_key"
    else
        EXIT_CODE=$?
        if [ $EXIT_CODE -eq 1 ]; then
            echo "✓ Registry client works (key not found is expected)"
        else
            echo "✗ Registry client error (exit code: $EXIT_CODE)"
        fi
    fi
else
    echo "✗ registry-get command not found"
fi

echo ""
echo "Test complete!"
