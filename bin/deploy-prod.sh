#!/usr/bin/env bash

SCRIPT_FOLDER="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

rsync \
  -avc \
  --exclude .DS_Store \
  --exclude .git/ \
  --exclude .idea/ \
  --exclude node_modules/ \
  --exclude var/cache/ \
  --exclude var/log/ \
  --exclude drivers/ \
  --exclude mcp-env/ \
  --exclude public/generated-content/ \
  --delete \
  "$SCRIPT_FOLDER"/../../ \
  www-data@54.193.105.168:/var/www/prod/

ssh www-data@54.193.105.168 -C ' \
cd ~/prod/playwright-mcp-cloud-webapp; \
/usr/bin/env php bin/console --env=prod cache:clear; \
/usr/bin/env php bin/console --env=prod doctrine:database:create --if-not-exists --no-interaction; \
/usr/bin/env php bin/console --env=prod doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing;
'
