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
  www-data@152.53.168.103:/var/www/prod/

ssh www-data@152.53.168.103 -C ' \
cd ~/prod/maas-webapp; \
/usr/bin/env php bin/console --env=prod cache:clear; \
/usr/bin/env php bin/console --env=prod doctrine:database:create --if-not-exists --no-interaction; \
/usr/bin/env php bin/console --env=prod doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing;
'
