#!/usr/bin/env bash
#@metadata-start
#@name frontend
#@description Build the frontend (Tailwind, Asset Map)
#@source-repo git@github.com:Enterprise-Tooling-for-Symfony/dxcli-commands-app.git
#@source-commit-id 6c91eb2f8b711933857f1e5691364440507f3c16
#@metadata-end

# >>> BEGIN SETUP - always keep this section in your scripts!
set -e
set -u
set -o pipefail

SOURCE=${BASH_SOURCE[0]}
while [ -L "$SOURCE" ]; do
    DIR=$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )
    SOURCE=$(readlink "$SOURCE")
    [[ $SOURCE != /* ]] && SOURCE=$DIR/$SOURCE
done

SCRIPT_FOLDER=$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )
if [ -z "$SCRIPT_FOLDER" ]; then
    echo "Failed to determine script location" >&2
    exit 1
fi

PROJECT_ROOT=$( cd "$SCRIPT_FOLDER/../.." >/dev/null 2>&1 && pwd )
if [ -z "$PROJECT_ROOT" ]; then
    echo "Failed to determine dxcli root" >&2
    exit 1
fi

source "$PROJECT_ROOT/.dxcli/shared.sh"
# <<< END SETUP - from now on, use $PROJECT_ROOT to get the full path to your project's root folder.


# Validate environment
require_command php

rm -rf "$PROJECT_ROOT/public/assets"
/usr/bin/env php "$PROJECT_ROOT/bin/console" tailwind:build
/usr/bin/env php "$PROJECT_ROOT/bin/console" asset-map:compile
