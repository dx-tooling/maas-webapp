#!/usr/bin/env python3
"""
MCP Instance Data Registry Client

This module provides functions to interact with the MCP Instance Data Registry
from within a Docker container.

Usage:
    import registry_client
    value = registry_client.get('database_url')

Or from command line:
    python mcp_instance_data_registry_client.py <key>
"""

import os
import sys
import json
import urllib.request
import urllib.error


def get(key):
    """
    Retrieve a value from the MCP Instance Data Registry.

    Args:
        key: The key to retrieve

    Returns:
        The value associated with the key, or None if not found

    Raises:
        RuntimeError: If environment variables are missing or authentication fails
    """
    # Get environment variables
    registry_endpoint = os.environ.get('MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT')
    registry_bearer = os.environ.get('MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER')
    instance_uuid = os.environ.get('MAAS_MCP_INSTANCE_UUID')

    # Validate environment variables
    if not registry_endpoint:
        raise RuntimeError('MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT environment variable is not set')
    if not registry_bearer:
        raise RuntimeError('MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER environment variable is not set')
    if not instance_uuid:
        raise RuntimeError('MAAS_MCP_INSTANCE_UUID environment variable is not set')

    # Build the URL
    url = f"{registry_endpoint}/{key}"

    # Create the request with authentication
    request = urllib.request.Request(url)
    request.add_header('Authorization', f'Bearer {registry_bearer}')

    try:
        # Make the request
        with urllib.request.urlopen(request) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('value')

    except urllib.error.HTTPError as e:
        if e.code == 404:
            return None  # Key not found
        elif e.code == 401:
            raise RuntimeError('Authentication failed')
        else:
            raise RuntimeError(f'HTTP error {e.code}: {e.reason}')
    except Exception as e:
        raise RuntimeError(f'Failed to get registry value: {e}')


def get_all():
    """
    Retrieve all values from the MCP Instance Data Registry for this instance.

    Returns:
        A dictionary of all key-value pairs

    Raises:
        RuntimeError: If environment variables are missing or authentication fails
    """
    # Get environment variables
    registry_endpoint = os.environ.get('MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT')
    registry_bearer = os.environ.get('MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER')
    instance_uuid = os.environ.get('MAAS_MCP_INSTANCE_UUID')

    # Validate environment variables
    if not registry_endpoint:
        raise RuntimeError('MAAS_MCP_INSTANCE_DATA_REGISTRY_ENDPOINT environment variable is not set')
    if not registry_bearer:
        raise RuntimeError('MAAS_MCP_INSTANCE_DATA_REGISTRY_BEARER environment variable is not set')
    if not instance_uuid:
        raise RuntimeError('MAAS_MCP_INSTANCE_UUID environment variable is not set')

    # Build the URL (without a key to get all values)
    url = registry_endpoint

    # Create the request with authentication
    request = urllib.request.Request(url)
    request.add_header('Authorization', f'Bearer {registry_bearer}')

    try:
        # Make the request
        with urllib.request.urlopen(request) as response:
            data = json.loads(response.read().decode('utf-8'))
            return data.get('values', {})

    except urllib.error.HTTPError as e:
        if e.code == 401:
            raise RuntimeError('Authentication failed')
        else:
            raise RuntimeError(f'HTTP error {e.code}: {e.reason}')
    except Exception as e:
        raise RuntimeError(f'Failed to get registry values: {e}')


if __name__ == '__main__':
    # Command-line interface
    if len(sys.argv) < 2:
        print('Usage: python mcp_instance_data_registry_client.py <key>', file=sys.stderr)
        print('   or: python mcp_instance_data_registry_client.py --all', file=sys.stderr)
        sys.exit(1)

    try:
        if sys.argv[1] == '--all':
            values = get_all()
            print(json.dumps(values, indent=2))
        else:
            value = get(sys.argv[1])
            if value is not None:
                print(value)
            else:
                print(f"Key '{sys.argv[1]}' not found in registry", file=sys.stderr)
                sys.exit(1)
    except RuntimeError as e:
        print(f'Error: {e}', file=sys.stderr)
        sys.exit(1)
