#!/usr/bin/env bash
#@metadata-start
#@source-repo git@github.com:Enterprise-Tooling-for-Symfony/dxcli-commands-app.git
#@source-commit-id 6c91eb2f8b711933857f1e5691364440507f3c16
#@metadata-end
set -e

SCRIPT_FOLDER="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

# Check if APP_ENV is set before using it
if [[ -n "${APP_ENV:-}" ]]; then
    ENV="$APP_ENV"
else
    ENV="dev"
fi

[ -f "${SCRIPT_FOLDER}/../../.env" ] && source "${SCRIPT_FOLDER}/../../.env" || true
[ -f "${SCRIPT_FOLDER}/../../.env.local" ] && source "${SCRIPT_FOLDER}/../../.env.local" || true
[ -f "${SCRIPT_FOLDER}/../../.env.${ENV}" ] && source "${SCRIPT_FOLDER}/../../.env.${ENV}" || true
[ -f "${SCRIPT_FOLDER}/../../.env.${ENV}.local" ] && source "${SCRIPT_FOLDER}/../../.env.${ENV}.local" || true
