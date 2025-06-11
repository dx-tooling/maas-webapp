#!/usr/bin/env bash

cd /var/www/prod/mcp-env
source $HOME/.nvm/nvm.sh

export DISPLAY=:$1
nohup npx @playwright/mcp@latest \
    --port $2 \
    --no-sandbox \
    --isolated \
    --browser chromium \
    --host 0.0.0.0 \
    >/var/tmp/launchPlaywrightMcp.$2.log \
    2>&1 \
    &
