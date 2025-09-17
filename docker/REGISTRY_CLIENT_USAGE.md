# MCP Instance Data Registry Client - Container Usage Guide

This guide explains how to use the MCP Instance Data Registry from within running MCP instance containers.

## Overview

Each MCP instance container has access to a secure key-value data registry that allows the container to retrieve configuration and runtime data from the MaaS platform. This enables dynamic configuration without rebuilding containers.

## Environment Variables

When a container is launched, the following environment variables are automatically set:

- `MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT`: The base URL of the registry API for this instance
- `MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER`: The authentication token (unique per instance)
- `MAAS_MCP_INSTANCE_UUID`: The UUID of this instance

These variables are automatically passed by the platform when creating containers through the `ContainerManagementDomainService`.

## Using the Registry Client

### From Bash Scripts

The simplest way to access the registry is using the `registry-get` command:

```bash
#!/bin/bash

# Get a single value
DATABASE_URL=$(registry-get database_url)

# Check if the value was retrieved successfully
if [ $? -eq 0 ]; then
    echo "Database URL: $DATABASE_URL"
else
    echo "Failed to retrieve database_url"
fi

# Use the value in your application
export DATABASE_URL
```

### From Node.js Applications

For Node.js applications, you can use the registry client directly:

```javascript
// Using shell command
const { exec } = require('child_process');
const util = require('util');
const execPromise = util.promisify(exec);

async function getRegistryValue(key) {
    try {
        const { stdout } = await execPromise(`registry-get ${key}`);
        return stdout.trim();
    } catch (error) {
        console.error(`Failed to get ${key}:`, error);
        return null;
    }
}

// Usage
const dbUrl = await getRegistryValue('database_url');
```

### From Python Applications

For Python applications:

```python
import subprocess
import os

def get_registry_value(key):
    """Get a value from the MCP registry."""
    try:
        result = subprocess.run(
            ['registry-get', key],
            capture_output=True,
            text=True,
            check=True
        )
        return result.stdout.strip()
    except subprocess.CalledProcessError as e:
        print(f"Failed to get {key}: {e}")
        return None

# Usage
database_url = get_registry_value('database_url')
```

## Testing the Registry Client

To verify that the registry client is properly configured in your container:

```bash
# Run the test script
test-registry

# This will output:
# ✓ MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT is set
# ✓ MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER is set  
# ✓ MAAS_MCP_INSTANCE_UUID is set
# ✓ registry-get command is available
```

## Common Use Cases

### 1. Database Configuration
Store database connection strings, credentials, and configuration:
```bash
DB_HOST=$(registry-get db_host)
DB_PORT=$(registry-get db_port)
DB_NAME=$(registry-get db_name)
DB_USER=$(registry-get db_user)
DB_PASS=$(registry-get db_password)
```

### 2. API Keys and Secrets
Retrieve API keys and secrets securely:
```bash
API_KEY=$(registry-get external_api_key)
JWT_SECRET=$(registry-get jwt_secret)
```

### 3. Feature Flags
Control feature availability dynamically:
```bash
if [ "$(registry-get feature_new_ui)" = "true" ]; then
    # Enable new UI
fi
```

### 4. Runtime Configuration
Adjust application behavior without rebuilding:
```bash
LOG_LEVEL=$(registry-get log_level)
MAX_WORKERS=$(registry-get max_workers)
CACHE_TTL=$(registry-get cache_ttl)
```

## Security Notes

1. **Authentication**: Each instance has its own unique bearer token (`MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER`) that authenticates requests to the registry.

2. **Isolation**: Each instance can only access its own data. The registry enforces instance-level isolation.

3. **Transport Security**: All registry requests use HTTPS in production environments.

4. **Token Protection**: Never log or expose the `MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER` token. It's automatically injected by the platform.

## Troubleshooting

### Environment Variables Not Set
If environment variables are missing, the container was likely started manually instead of through the platform. Ensure containers are created via the MaaS web interface or API.

### Authentication Failures
If you get authentication errors:
1. Verify the instance is properly registered in the database
2. Check that the `registryBearer` field is set for the instance
3. Ensure the token hasn't been modified or corrupted

### Key Not Found
If a key returns empty or error:
1. The key might not be set in the registry yet
2. Use the MaaS admin interface to set registry values
3. Keys are case-sensitive - verify the exact key name

### Network Issues
If you can't reach the registry endpoint:
1. Check that the container is on the correct network
2. Verify the `MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT` URL is accessible
3. Ensure Traefik routing is properly configured

## Best Practices

1. **Graceful Fallbacks**: Always handle the case where a registry key might not exist:
   ```bash
   VALUE=$(registry-get my_key || echo "default_value")
   ```

2. **Cache Values**: For frequently accessed values, cache them locally to reduce registry calls:
   ```bash
   if [ -z "$CACHED_VALUE" ]; then
       CACHED_VALUE=$(registry-get expensive_key)
   fi
   ```

3. **Validate Values**: Always validate retrieved values before using them:
   ```bash
   PORT=$(registry-get service_port)
   if ! [[ "$PORT" =~ ^[0-9]+$ ]]; then
       echo "Invalid port number"
       exit 1
   fi
   ```

4. **Document Keys**: Maintain documentation of all registry keys your application uses.

## Platform Integration

The registry is integrated with the MaaS platform at multiple levels:

1. **Container Creation**: Environment variables are automatically injected when containers are created
2. **Instance Management**: Registry data is tied to instance lifecycle
3. **Admin Interface**: Platform administrators can manage registry values through the web UI
4. **API Access**: Registry values can be managed via the platform API

For more information about the registry architecture, see the main [Registry Client README](_mcp-instance-data-registry-clients/README.md).
