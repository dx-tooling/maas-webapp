#!/usr/bin/env bash
#@metadata-start
#@name test
#@description Run the test suite
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

echo
log_info "Running unit tests..."
/usr/bin/env php "$PROJECT_ROOT/bin/phpunit" "$PROJECT_ROOT/tests/Unit"

echo
log_info "Running integration tests..."
/usr/bin/env php "$PROJECT_ROOT/bin/console" doctrine:database:drop --if-exists --force --env=test
/usr/bin/env php "$PROJECT_ROOT/bin/console" doctrine:database:create --env=test
/usr/bin/env php "$PROJECT_ROOT/bin/console" doctrine:migrations:migrate --no-interaction --env=test
/usr/bin/env php "$PROJECT_ROOT/bin/phpunit" "$PROJECT_ROOT/tests/Integration"

echo
log_info "Running application tests..."
/usr/bin/env php "$PROJECT_ROOT/bin/console" doctrine:database:drop --if-exists --force --env=test
/usr/bin/env php "$PROJECT_ROOT/bin/console" doctrine:database:create --env=test
/usr/bin/env php "$PROJECT_ROOT/bin/console" doctrine:migrations:migrate --no-interaction --env=test
/usr/bin/env php "$PROJECT_ROOT/bin/phpunit" "$PROJECT_ROOT/tests/Application"

log_info "All tests completed successfully! ✨"
