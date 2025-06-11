#!/usr/bin/env bash

SCRIPT_FOLDER="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

/usr/bin/sudo $SCRIPT_FOLDER/generate-mcp-proxies.sh >/var/tmp/generate-mcp-proxies.sh.log 2>&1 &
