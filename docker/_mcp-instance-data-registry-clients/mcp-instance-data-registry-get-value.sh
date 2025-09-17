#!/bin/bash
# MCP Instance Data Registry Client
# Usage: mcp-instance-data-registry-get-value.sh <key>
#
# This script retrieves a value from the MCP Instance Data Registry
# using the environment variables provided by the platform.
#
# Required environment variables:
#   - MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT: The base URL of the registry API
#   - MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER: The bearer token for authentication
#   - MAAS_MCP_INSTANCE_UUID: The UUID of this instance

set -euo pipefail

# Check if key is provided
if [ $# -eq 0 ]; then
    echo "Usage: $0 <key>" >&2
    echo "Example: $0 database_url" >&2
    exit 1
fi

KEY="$1"

# Check required environment variables
if [ -z "${MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT:-}" ]; then
    echo "Error: MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT environment variable is not set" >&2
    exit 1
fi

if [ -z "${MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER:-}" ]; then
    echo "Error: MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER environment variable is not set" >&2
    exit 1
fi

if [ -z "${MAAS_MCP_INSTANCE_UUID:-}" ]; then
    echo "Error: MAAS_MCP_INSTANCE_UUID environment variable is not set" >&2
    exit 1
fi

# Build the full URL
URL="${MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT}/${KEY}"

# Make the request with bearer authentication
RESPONSE=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer ${MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER}" "${URL}")
HTTP_CODE=$(echo "${RESPONSE}" | tail -n 1)
BODY=$(echo "${RESPONSE}" | head -n -1)

# Check the HTTP status code
if [ "${HTTP_CODE}" -eq 200 ]; then
    # Success - parse the JSON response and extract the value
    # Using simple grep/sed since jq might not be available
    echo "${BODY}" | grep -o '"value":"[^"]*"' | sed 's/"value":"//' | sed 's/"$//'
    exit 0
elif [ "${HTTP_CODE}" -eq 404 ]; then
    echo "Error: Key '${KEY}' not found in registry" >&2
    exit 1
elif [ "${HTTP_CODE}" -eq 401 ]; then
    echo "Error: Authentication failed" >&2
    exit 1
else
    echo "Error: Unexpected HTTP status code ${HTTP_CODE}" >&2
    echo "Response: ${BODY}" >&2
    exit 1
fi
