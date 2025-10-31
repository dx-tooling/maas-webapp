#!/usr/bin/env bash
#MISE description="Connect to the local application database"

set -e

SCRIPT_FOLDER="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

source "${SCRIPT_FOLDER}/../bin/_init.sh"

mysql -h"${DATABASE_HOST}" -u"${DATABASE_USER}" -p"${DATABASE_PASSWORD}" "${DATABASE_DB}" "$@"
