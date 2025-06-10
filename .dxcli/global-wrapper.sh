#!/usr/bin/env bash

# Find the nearest .dxcli/dxcli.sh by traversing up the directory tree
find_dxcli() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
        if [[ -f "$dir/.dxcli/dxcli.sh" ]]; then
            echo "$dir/.dxcli/dxcli.sh"
            return 0
        fi
        dir="$(dirname "$dir")"
    done
    return 1
}

# Find the DX CLI script
DXCLI_SCRIPT=$(find_dxcli)

if [[ -z "$DXCLI_SCRIPT" ]]; then
    echo "Error: No DX CLI installation found in current directory or any parent directory" >&2
    exit 1
fi

# Execute the project-specific DX CLI with all arguments
exec "$DXCLI_SCRIPT" "$@"
