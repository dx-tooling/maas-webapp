#!/usr/bin/env bash

set -e
set -u  # Treat unset variables as errors
set -o pipefail  # Pipeline fails on first failed command

# Resolve paths
SCRIPT_FOLDER="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
if [ -z "$SCRIPT_FOLDER" ]; then
    echo "Failed to determine script location" >&2
    exit 1
fi

PROJECT_ROOT="$( cd "$SCRIPT_FOLDER/.." >/dev/null 2>&1 && pwd )"
if [ -z "$PROJECT_ROOT" ]; then
    echo "Failed to determine project root" >&2
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging helpers
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1" >&2
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" >&2
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

# Validate required commands
require_command() {
    local cmd=$1
    if ! command -v "$cmd" >/dev/null 2>&1; then
        log_error "Required command not found: $cmd"
        exit 1
    fi
}

# Command discovery and metadata helpers
get_command_metadata() {
    local script_path=$1
    local in_metadata=0
    local name=""
    local description=""

    while IFS= read -r line; do
        # Start of metadata block
        if [[ "$line" == "#@metadata-start" ]]; then
            in_metadata=1
            continue
        fi

        # End of metadata block
        if [[ "$line" == "#@metadata-end" ]]; then
            break
        fi

        # Inside metadata block
        if [[ $in_metadata -eq 1 ]]; then
            # Parse name
            if [[ "$line" =~ ^#@name[[:space:]]*(.+)$ ]]; then
                name="${BASH_REMATCH[1]}"
            fi
            # Parse description
            if [[ "$line" =~ ^#@description[[:space:]]*(.+)$ ]]; then
                description="${BASH_REMATCH[1]}"
            fi
        fi
    done < "$script_path"

    # Return metadata as a formatted string
    if [[ -n "$name" && -n "$description" ]]; then
        echo "$name|$description"
    fi
}

# Get all available commands from a directory
get_commands() {
    local dir=$1
    local commands=()

    # Check if directory exists
    if [[ ! -d "$dir" ]]; then
        return
    fi

    # Find all executable shell scripts
    while IFS= read -r -d '' script; do
        local metadata
        metadata=$(get_command_metadata "$script")
        if [[ -n "$metadata" ]]; then
            commands+=("$metadata")
        fi
    done < <(find "$dir" -type f -name "*.sh" -print0)

    # Return commands as newline-separated list
    printf "%s\n" "${commands[@]}"
}

# Find all parent directories containing .dxcli installations
find_parent_dxcli_installations() {
    local current_dir="$PWD"
    local installations=()
    local current_installation="$SCRIPT_FOLDER"
    
    # Add the current installation first (highest priority)
    installations+=("$current_installation")
    
    # Find parent installations
    while [[ "$current_dir" != "/" ]]; do
        current_dir="$(dirname "$current_dir")"
        if [[ -d "$current_dir/.dxcli" && "$current_dir/.dxcli" != "$current_installation" ]]; then
            installations+=("$current_dir/.dxcli")
        fi
    done
    
    printf "%s\n" "${installations[@]}"
}

# Calculate Levenshtein distance between two strings
levenshtein_distance() {
    local str1=$1
    local str2=$2
    local len1=${#str1}
    local len2=${#str2}

    # Create a matrix of zeros
    declare -A matrix
    for ((i=0; i<=len1; i++)); do
        matrix[$i,0]=$i
    done
    for ((j=0; j<=len2; j++)); do
        matrix[0,$j]=$j
    done

    # Fill the matrix
    local cost
    for ((i=1; i<=len1; i++)); do
        for ((j=1; j<=len2; j++)); do
            if [[ "${str1:i-1:1}" == "${str2:j-1:1}" ]]; then
                cost=0
            else
                cost=1
            fi

            # Get minimum of three operations
            local del=$((matrix[$((i-1)),$j] + 1))
            local ins=$((matrix[$i,$((j-1))] + 1))
            local sub=$((matrix[$((i-1)),$((j-1))] + cost))

            # Find minimum
            matrix[$i,$j]=$del
            [[ $ins -lt ${matrix[$i,$j]} ]] && matrix[$i,$j]=$ins
            [[ $sub -lt ${matrix[$i,$j]} ]] && matrix[$i,$j]=$sub
        done
    done

    # Return final distance
    echo "${matrix[$len1,$len2]}"
}

# Find closest matching command
find_closest_command() {
    local input=$1
    local min_distance=1000
    local closest=""
    local all_commands=()
    local -A command_names=() # Use associative array to track unique command names

    # Get all installations for stacked subcommands
    local installations=()
    mapfile -t installations < <(find_parent_dxcli_installations)
    
    # Add built-in metacommands
    command_names[".install-commands"]=1
    command_names[".install-globally"]=1
    command_names[".update"]=1
    
    # Collect all command names from stacked subcommands
    for installation in "${installations[@]}"; do
        if [[ -d "$installation/subcommands" ]]; then
            while IFS= read -r -d '' script; do
                local metadata
                metadata=$(get_command_metadata "$script")
                if [[ -n "$metadata" ]]; then
                    local name="${metadata%%|*}"
                    command_names["$name"]=1
                fi
            done < <(find "$installation/subcommands" -type f -name "*.sh" -print0)
        fi
    done
    
    # Convert unique command names to array
    for cmd in "${!command_names[@]}"; do
        all_commands+=("$cmd")
    done

    # Find the closest match
    for cmd in "${all_commands[@]}"; do
        local distance
        distance=$(levenshtein_distance "$input" "$cmd")
        if [[ $distance -lt $min_distance ]]; then
            min_distance=$distance
            closest=$cmd
        fi
    done

    # Only suggest if reasonably close (adjust threshold as needed)
    if [[ $min_distance -le 3 ]]; then
        echo "$closest"
    fi
}
