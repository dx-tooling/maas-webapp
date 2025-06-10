#!/usr/bin/env bash

set -e
set -u
set -o pipefail

# Resolve the actual script location, even when called through a symlink
SOURCE=${BASH_SOURCE[0]}
if [ -z "$SOURCE" ]; then
    echo "Failed to determine script source" >&2
    exit 1
fi

while [ -L "$SOURCE" ]; do
    DIR=$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )
    if [ -z "$DIR" ]; then
        echo "Failed to resolve symlink directory" >&2
        exit 1
    fi
    SOURCE=$(readlink "$SOURCE")
    [[ $SOURCE != /* ]] && SOURCE=$DIR/$SOURCE
done

SCRIPT_FOLDER=$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )
if [ -z "$SCRIPT_FOLDER" ]; then
    echo "Failed to determine script folder" >&2
    exit 1
fi

source "$SCRIPT_FOLDER/shared.sh"

#
# METACOMMAND IMPLEMENTATIONS
#

# .install-commands - Install subcommands from a git repository
metacommand_install_commands() {
    # Function to install commands from a repository URL
    install_from_repo() {
        local REPO_URL="$1"
        local TEMP_DIR
        TEMP_DIR=$(mktemp -d)
        local SUBCOMMANDS_DIR="$PROJECT_ROOT/.dxcli/subcommands"

        # Validate git command
        require_command git

        # Clone the repository
        log_info "Cloning repository $REPO_URL..."
        if ! git clone "$REPO_URL" "$TEMP_DIR" >/dev/null 2>&1; then
            log_error "Failed to clone repository"
            rm -rf "$TEMP_DIR"
            return 1
        fi

        # Get the latest commit ID from the main/master branch
        cd "$TEMP_DIR"
        COMMIT_ID=$(git rev-parse HEAD)
        cd - > /dev/null

        # Check if subcommands directory exists in the cloned repo
        if [ ! -d "$TEMP_DIR/subcommands" ]; then
            log_error "No subcommands directory found in the repository"
            rm -rf "$TEMP_DIR"
            return 1
        fi

        # Create subcommands directory if it doesn't exist
        mkdir -p "$SUBCOMMANDS_DIR"

        # Get list of subcommands in the repo
        REPO_SUBCOMMANDS=()
        while IFS= read -r -d '' script; do
            REPO_SUBCOMMANDS+=("$(basename "$script")")
        done < <(find "$TEMP_DIR/subcommands" -type f -name "*.sh" -print0)

        # Copy all subcommands
        log_info "Installing subcommands..."
        cp -R "$TEMP_DIR/subcommands/"* "$SUBCOMMANDS_DIR/"

        # Add source metadata to each subcommand that was just installed
        log_info "Adding source metadata to subcommands..."
        for script_name in "${REPO_SUBCOMMANDS[@]}"; do
            script="$SUBCOMMANDS_DIR/$script_name"
            if [ -f "$script" ]; then
                # Check if the file has a metadata section
                if grep -q "#@metadata-start" "$script"; then
                    # Remove any existing source metadata lines to avoid duplication
                    sed -i.bak "/#@source-repo/d" "$script"
                    sed -i.bak "/#@source-commit-id/d" "$script"
                    # Add source metadata before metadata-end
                    sed -i.bak "/#@metadata-end/i\\
#@source-repo $REPO_URL\\
#@source-commit-id $COMMIT_ID\\
" "$script"
                    rm -f "${script}.bak"
                else
                    # If no metadata section exists, add one
                    sed -i.bak "1a\\
#@metadata-start\\
#@source-repo $REPO_URL\\
#@source-commit-id $COMMIT_ID\\
#@metadata-end" "$script"
                    rm -f "${script}.bak"
                fi
            fi
        done

        # Make all scripts executable
        find "$SUBCOMMANDS_DIR" -type f -name "*.sh" -exec chmod +x {} \;

        log_info "Successfully installed $(echo ${#REPO_SUBCOMMANDS[@]}) subcommands from $REPO_URL (commit: $COMMIT_ID)"

        # Clean up manually
        rm -rf "$TEMP_DIR"

        return 0
    }

    # Check if a URL was provided as an argument
    if [ $# -eq 1 ]; then
        # Install from the provided URL
        install_from_repo "$1"
        return $?
    fi

    # No URL provided, check for .dxclirc file
    if [ $# -eq 0 ]; then
        DXCLIRC_FILE="$PROJECT_ROOT/.dxclirc"

        if [ ! -f "$DXCLIRC_FILE" ]; then
            log_error "No repository URL provided and no .dxclirc file found."
            log_error "Usage: dx .install-commands <git-repository-url>"
            return 1
        fi

        log_info "No URL provided. Looking for URLs in .dxclirc file..."

        # Flag to track if we're in the install-commands section
        in_install_commands=0
        # Flag to track if we found any URLs
        found_urls=0

        # Read the .dxclirc file line by line
        while IFS= read -r line || [ -n "$line" ]; do
            # Remove leading/trailing whitespace
            line=$(echo "$line" | xargs)

            # Skip empty lines and comments
            if [ -z "$line" ] || [[ "$line" == \#* ]]; then
                continue
            fi

            # Check for section headers
            if [[ "$line" == \[*\] ]]; then
                if [ "$line" == "[install-commands]" ]; then
                    in_install_commands=1
                    log_info "Found [install-commands] section"
                else
                    in_install_commands=0
                fi
                continue
            fi

            # Process git URLs in the install-commands section
            if [ $in_install_commands -eq 1 ] && [ -n "$line" ]; then
                log_info "Installing commands from: $line"
                install_from_repo "$line"
                found_urls=1
            fi
        done < "$DXCLIRC_FILE"

        if [ $found_urls -eq 0 ]; then
            log_error "No repository URLs found in the [install-commands] section of .dxclirc"
            log_error "Usage: dx .install-commands <git-repository-url>"
            return 1
        fi

        return 0
    fi

    # If we get here, wrong number of arguments was provided
    log_error "Usage: dx .install-commands <git-repository-url>"
    return 1
}

# .install-globally - Install a dxcli wrapper script globally (run once per user)
metacommand_install_globally() {
    # Path to the global wrapper script
    GLOBAL_WRAPPER_SRC="$SCRIPT_FOLDER/global-wrapper.sh"
    # Path to the zsh completion script
    ZSH_COMPLETION_SRC="$SCRIPT_FOLDER/_dx"

    # Check if the global wrapper script exists
    if [ ! -f "$GLOBAL_WRAPPER_SRC" ]; then
        log_error "Global wrapper script not found at: $GLOBAL_WRAPPER_SRC"
        return 1
    fi

    # Check if the zsh completion script exists
    if [ ! -f "$ZSH_COMPLETION_SRC" ]; then
        log_warning "ZSH completion script not found at: $ZSH_COMPLETION_SRC"
        log_warning "Autocompletion will not be available"
    fi

    # Determine the appropriate bin directory
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS - prefer /usr/local/bin
        BIN_DIR="/usr/local/bin"
        # Create if it doesn't exist
        if [[ ! -d "$BIN_DIR" ]]; then
            log_info "Creating $BIN_DIR directory (requires sudo)..."
            sudo mkdir -p "$BIN_DIR"
        fi
    else
        # Linux - use ~/.local/bin
        BIN_DIR="$HOME/.local/bin"
        mkdir -p "$BIN_DIR"
    fi

    # Install the wrapper script
    WRAPPER_PATH="$BIN_DIR/dx"

    # Copy the global wrapper script to its final location
    if [[ "$OSTYPE" == "darwin"* ]]; then
        log_info "Installing wrapper script (requires sudo)..."
        sudo cp "$GLOBAL_WRAPPER_SRC" "$WRAPPER_PATH"
        sudo chmod 755 "$WRAPPER_PATH"
        sudo chown root:wheel "$WRAPPER_PATH"
    else
        cp "$GLOBAL_WRAPPER_SRC" "$WRAPPER_PATH"
        chmod +x "$WRAPPER_PATH"
    fi

    # Install zsh completion if available
    if [ -f "$ZSH_COMPLETION_SRC" ]; then
        # Create zsh completion directory if it doesn't exist
        ZSH_COMPLETION_DIR="$HOME/.zsh/completion"
        mkdir -p "$ZSH_COMPLETION_DIR"

        # Copy the completion script
        log_info "Installing zsh completion script..."
        cp "$ZSH_COMPLETION_SRC" "$ZSH_COMPLETION_DIR/_dx"
        chmod +x "$ZSH_COMPLETION_DIR/_dx"

        # Check if the completion directory is in fpath
        if ! grep -q "fpath=(\$fpath $ZSH_COMPLETION_DIR)" "$HOME/.zshrc" 2>/dev/null; then
            log_info "Adding completion directory to fpath in .zshrc..."
            echo "" >> "$HOME/.zshrc"
            echo "# Add dxcli completion" >> "$HOME/.zshrc"
            echo "fpath=(\$fpath $ZSH_COMPLETION_DIR)" >> "$HOME/.zshrc"
            echo "autoload -Uz compinit && compinit" >> "$HOME/.zshrc"
            log_warning "Please restart your shell or run: source ~/.zshrc"
        fi
    fi

    # Ensure BIN_DIR is in PATH
    if [[ ":$PATH:" != *":$BIN_DIR:"* ]]; then
        # Determine shell configuration file
        if [[ -n "${ZSH_VERSION:-}" ]]; then
            SHELL_RC="$HOME/.zshrc"
        else
            SHELL_RC="$HOME/.bashrc"
        fi

        # Add to PATH if not already there
        echo "export PATH=\"\$PATH:$BIN_DIR\"" >> "$SHELL_RC"
        log_info "Added $BIN_DIR to PATH in $SHELL_RC"
        log_warning "Please restart your shell or run: source $SHELL_RC"
    fi

    log_info "DX CLI wrapper installed successfully at: $WRAPPER_PATH"
    if [ -f "$ZSH_COMPLETION_SRC" ]; then
        log_info "ZSH completion installed at: $ZSH_COMPLETION_DIR/_dx"
    fi
    log_info "You can now use 'dx' command from any directory within your project"

    return 0
}

# .update - Update the dxcli installation in the current project
metacommand_update() {
    # Validate git command
    require_command git

    # Define repository URL and temporary directory
    REPO_URL="https://github.com/dx-tooling/dxcli.git"
    TEMP_DIR=$(mktemp -d)
    DXCLI_DIR="$PROJECT_ROOT/.dxcli"

    # Ensure cleanup on exit if we don't proceed with the update
    cleanup() {
        rm -rf "$TEMP_DIR"
    }
    trap cleanup EXIT

    log_info "Preparing to update dxcli installation..."

    # Clone the repository to get the latest version
    log_info "Fetching latest version from $REPO_URL..."
    if ! git clone --depth 1 "$REPO_URL" "$TEMP_DIR" >/dev/null 2>&1; then
        log_error "Failed to clone repository"
        return 1
    fi

    # Create a wrapper script that will perform the actual update
    UPDATE_WRAPPER="/tmp/dxcli_update_wrapper_$(date +%s).sh"
    cat > "$UPDATE_WRAPPER" << EOF
#!/usr/bin/env bash
set -e

# Wait a moment for the original process to exit
sleep 1

# Define paths
TEMP_DIR="$TEMP_DIR"
DXCLI_DIR="$DXCLI_DIR"
REPO_URL="$REPO_URL"

# Create backup of current installation
BACKUP_DIR=\$(mktemp -d)/dxcli-backup-\$(date +%Y%m%d%H%M%S)
echo "Creating backup of current installation at \$BACKUP_DIR..."
mkdir -p "\$BACKUP_DIR"
cp -R "\$DXCLI_DIR" "\$BACKUP_DIR"

# List of files/directories to preserve (user customizations)
PRESERVE=(
    "subcommands"
)

# Temporarily move preserved directories
for item in "\${PRESERVE[@]}"; do
    if [ -e "\$DXCLI_DIR/\$item" ]; then
        echo "Preserving your custom \$item..."
        mv "\$DXCLI_DIR/\$item" "\$TEMP_DIR/\$item.preserved"
    fi
done

# Copy new files
echo "Installing updated files..."
cp -R "\$TEMP_DIR/.dxcli/"* "\$DXCLI_DIR/"

# Restore preserved directories
for item in "\${PRESERVE[@]}"; do
    if [ -e "\$TEMP_DIR/\$item.preserved" ]; then
        echo "Restoring your custom \$item..."
        rm -rf "\${DXCLI_DIR:?}/\${item:?}"
        mv "\$TEMP_DIR/\$item.preserved" "\$DXCLI_DIR/\$item"
    fi
done

# Check if the ZSH completion script was updated
if [ -f "\$TEMP_DIR/.dxcli/_dx" ]; then
    # Check if global completion is installed
    ZSH_COMPLETION_DIR="\$HOME/.zsh/completion"
    if [ -f "\$ZSH_COMPLETION_DIR/_dx" ]; then
        echo "Updating ZSH completion script..."
        cp "\$TEMP_DIR/.dxcli/_dx" "\$ZSH_COMPLETION_DIR/_dx"
        chmod +x "\$ZSH_COMPLETION_DIR/_dx"
    fi
fi

# Make all scripts executable
find "\$DXCLI_DIR" -type f -name "*.sh" -exec chmod +x {} \;
# Make completion script executable if it exists
if [ -f "\$DXCLI_DIR/_dx" ]; then
    chmod +x "\$DXCLI_DIR/_dx"
fi

echo "dxcli has been successfully updated!"
echo "Your previous installation was backed up to \$BACKUP_DIR"
echo "If you encounter any issues, you can restore from the backup."

# Clean up
rm -rf "\$TEMP_DIR"
rm -f "\$0"  # Remove this wrapper script
EOF

    chmod +x "$UPDATE_WRAPPER"

    log_info "Ready to update. The update will run after this command completes."
    log_info "To proceed with the update, press Enter. To cancel, press Ctrl+C."
    read -r

    # Disable the cleanup trap since we want to keep the temp directory for the wrapper
    trap - EXIT

    # Execute the wrapper script and exit
    log_info "Starting update process..."
    "$UPDATE_WRAPPER" &

    log_info "Update process initiated. Please wait a moment while it completes."
    exit 0
}

#
# COMMAND HANDLING FUNCTIONS
#

# Print a section of commands in the help output
print_command_section() {
    local title=$1
    local -n commands=$2  # nameref to array
    local padding=${3:-12}

    echo -e "\n$title:"
    for cmd in "${commands[@]}"; do
        IFS='|' read -r name description <<< "$cmd"
        printf "    %-${padding}s %s\n" "$name" "$description"
    done
}

# Get stacked subcommands from current and parent .dxcli installations
get_stacked_subcommands() {
    local -A command_map=()  # Use associative array to track unique commands
    local installations=()

    # Get all parent installations (ordered by priority)
    mapfile -t installations < <(find_parent_dxcli_installations)

    # Process each installation, with closer ones taking precedence
    for installation in "${installations[@]}"; do
        local subcommands_dir="$installation/subcommands"

        if [[ -d "$subcommands_dir" ]]; then
            while IFS= read -r cmd_info; do
                if [[ -n "$cmd_info" ]]; then
                    IFS='|' read -r name description <<< "$cmd_info"
                    # Only add if not already in the map (closer ones take precedence)
                    if [[ -z "${command_map[$name]:-}" ]]; then
                        command_map[$name]="$cmd_info"
                    fi
                fi
            done < <(get_commands "$subcommands_dir")
        fi
    done

    # Output the unique commands sorted alphabetically by name
    local sorted_commands=()
    for name in $(printf '%s\n' "${!command_map[@]}" | sort); do
        echo "${command_map[$name]}"
    done
}

# Get all available metacommands
get_metacommands() {
    # Define the available metacommands with their descriptions
    local metacmds=(
        ".install-commands|Install subcommands from a git repository"
        ".install-globally|Install a dxcli wrapper script globally (run once per user)"
        ".update|Update the dxcli installation in the current project"
    )

    # Sort metacommands alphabetically
    printf '%s\n' "${metacmds[@]}" | sort
}

# Show help message with available commands
show_help() {
    local subcommands
    local metacommands

    # Get all commands
    mapfile -t subcommands < <(get_stacked_subcommands)
    mapfile -t metacommands < <(get_metacommands)

    # Calculate padding based on longest command name
    local max_length=0
    for cmd in "${subcommands[@]}" "${metacommands[@]}"; do
        IFS='|' read -r name _ <<< "$cmd"
        (( ${#name} > max_length )) && max_length=${#name}
    done
    local padding=$(( max_length + 2 ))

    cat << EOF
Developer Experience CLI

Usage: dx <subcommand>
EOF

    # Print command sections with dynamic padding
    [[ ${#subcommands[@]} -gt 0 ]] && print_command_section "Available subcommands" subcommands "$padding"
    [[ ${#metacommands[@]} -gt 0 ]] && print_command_section "Metacommands" metacommands "$padding"
    echo
}

# Find a stacked subcommand script by its name
find_stacked_subcommand_script() {
    local cmd=$1
    local installations=()

    # Get all parent installations (ordered by priority)
    mapfile -t installations < <(find_parent_dxcli_installations)

    # Search in each installation, with closer ones taking precedence
    for installation in "${installations[@]}"; do
        local subcommands_dir="$installation/subcommands"
        local script_path=""

        script_path=$(find_command_script "$cmd" "$subcommands_dir") || true
        if [[ -n "$script_path" ]]; then
            echo "$script_path"
            return 0
        fi
    done

    return 1
}

# Find a command script by its metadata name
find_command_script() {
    local cmd=$1
    local dir=$2
    local script_path=""

    if [[ ! -d "$dir" ]]; then
        return 1
    fi

    while IFS= read -r -d '' script; do
        local metadata
        metadata=$(get_command_metadata "$script")
        if [[ -n "$metadata" ]]; then
            IFS='|' read -r name _ <<< "$metadata"
            if [[ "$name" == "$cmd" ]]; then
                echo "$script"
                return 0
            fi
        fi
    done < <(find "$dir" -type f -name "*.sh" -print0)

    return 1
}

# Execute a command by name
execute_command() {
    local cmd=$1
    shift  # Remove the command name from the arguments

    # Special case for help
    if [[ "$cmd" == "help" || -z "$cmd" ]]; then
        show_help
        return
    fi

    # Check if it's a metacommand (starts with a dot)
    if [[ "$cmd" == .* ]]; then
        # Execute the corresponding metacommand function
        case "$cmd" in
            ".install-commands")
                log_info "Running: Install subcommands from a git repository"
                metacommand_install_commands "$@"
                ;;
            ".install-globally")
                log_info "Running: Install a dxcli wrapper script globally (run once per user)"
                metacommand_install_globally "$@"
                ;;
            ".update")
                log_info "Running: Update the dxcli installation in the current project"
                metacommand_update "$@"
                ;;
            *)
                log_error "Unknown metacommand: $cmd"

                # Try to find a suggestion
                local suggestion
                suggestion=$(find_closest_command "$cmd")
                if [[ -n "$suggestion" ]]; then
                    log_warning "Did you mean '$suggestion'?"
                    echo
                fi

                show_help
                exit 1
                ;;
        esac
        return
    fi

    # It's a regular subcommand, find the script
    local script_path=""
    script_path=$(find_stacked_subcommand_script "$cmd") || true

    if [[ -z "$script_path" ]]; then
        log_error "Unknown command: $cmd"

        # Try to find a suggestion
        local suggestion
        suggestion=$(find_closest_command "$cmd")
        if [[ -n "$suggestion" ]]; then
            log_warning "Did you mean '$suggestion'?"
            echo
        fi

        show_help
        exit 1
    fi

    # Execute the command
    local metadata
    metadata=$(get_command_metadata "$script_path")
    IFS='|' read -r name description <<< "$metadata"
    log_info "Running: $description"
    /usr/bin/env bash "$script_path" "$@"  # Pass all remaining arguments to the script
}

# Validate environment
require_command php
require_command npm

# Main execution
if [ $# -eq 0 ]; then
    execute_command "help"
else
    cmd="$1"
    shift
    execute_command "$cmd" "$@"
fi
