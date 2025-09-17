# MCP Instance Data Registry Client

This directory contains client libraries and scripts for accessing the MCP Instance Data Registry from within Docker containers.

## Overview

The MCP Instance Data Registry allows running Docker containers to retrieve configuration data from the application platform using a key-value store paradigm. Each instance has its own isolated namespace and authenticates using a bearer token.

## Environment Variables

The following environment variables are automatically provided to each container by the platform:

- `MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT`: The base URL of the registry API (e.g., `https://app.mcp-as-a-service.com/api/instance-registry/{instance-id}`)
- `MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER`: The bearer token for authenticating with the registry (separate from MCP bearer)
- `MAAS_MCP_INSTANCE_UUID`: The UUID of this instance

## Available Clients

### Bash Script (`mcp-instance-data-registry-get-value.sh`)

Simple bash script for retrieving values from the registry. Requires `curl`.

```bash
# Get a single value
./mcp-instance-data-registry-get-value.sh database_url

# Use in a script
DB_URL=$(./mcp-instance-data-registry-get-value.sh database_url)
if [ $? -eq 0 ]; then
    echo "Database URL: ${DB_URL}"
else
    echo "Failed to get database URL"
fi
```

### Python Module (`mcp_instance_data_registry_client.py`)

Python 3 client with no external dependencies.

```python
import registry_client

# Get a single value
db_url = registry_client.get('database_url')
if db_url:
    print(f"Database URL: {db_url}")

# Get all values
all_values = registry_client.get_all()
for key, value in all_values.items():
    print(f"{key}: {value}")
```

Command-line usage:
```bash
# Get a single value
python mcp_instance_data_registry_client.py database_url

# Get all values
python mcp_instance_data_registry_client.py --all
```

### Node.js Module (`mcp-instance-data-registry-client.js`)

Node.js client using only built-in modules.

```javascript
const registry = require('./registry-client');

// Get a single value (async)
const dbUrl = await registry.get('database_url');
if (dbUrl) {
    console.log(`Database URL: ${dbUrl}`);
}

// Get all values (async)
const allValues = await registry.getAll();
console.log(allValues);
```

Command-line usage:
```bash
# Get a single value
node mcp-instance-data-registry-client.js database_url

# Get all values
node mcp-instance-data-registry-client.js --all
```

## Integration in Dockerfiles

To use these clients in your Docker images, copy the appropriate client into your container:

```dockerfile
# For bash script
COPY mcp-instance-data-registry-get-value.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/mcp-instance-data-registry-get-value.sh

# For Python module
COPY mcp_instance_data_registry_client.py /usr/local/lib/

# For Node.js module
COPY mcp-instance-data-registry-client.js /app/
```

## Example Use Cases

### 1. Dynamic Configuration

Store configuration that might change between deployments:

```bash
# In container startup script
API_ENDPOINT=$(mcp-instance-data-registry-get-value.sh api_endpoint)
API_KEY=$(mcp-instance-data-registry-get-value.sh api_key)
export API_ENDPOINT API_KEY
```

### 2. Feature Flags

Enable/disable features dynamically:

```python
import registry_client

if registry_client.get('enable_new_feature') == 'true':
    enable_new_feature()
```

### 3. Resource URLs

Store URLs for external resources:

```javascript
const registry = require('./registry-client');

const s3Bucket = await registry.get('s3_bucket_url');
const cdnUrl = await registry.get('cdn_url');
```

## Security

- The bearer token is unique per instance and should never be exposed outside the container
- All communication with the registry is authenticated
- Each instance can only access its own data namespace
- The registry endpoint uses HTTPS in production

## Error Handling

All clients handle common error conditions:

- Missing environment variables
- Authentication failures (401)
- Key not found (404)
- Network errors
- Invalid JSON responses

Make sure to handle these errors appropriately in your application code.

## Testing

To test the registry client locally:

1. Set the required environment variables:
```bash
export MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT="https://app.mcp-as-a-service.com/api/instance-registry/test-instance"
export MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER="test-bearer-token"
export MAAS_MCP_INSTANCE_UUID="test-instance"
```

2. Run the client:
```bash
./mcp-instance-data-registry-get-value.sh test_key
```

Note: You'll need a valid instance and bearer token for actual testing.
