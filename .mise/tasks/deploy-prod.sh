#!/usr/bin/env bash
#MISE description="Deploy to the production systems"

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
  ./ \
  www-data@152.53.168.103:/var/www/prod/maas-webapp/

ssh www-data@152.53.168.103 -C ' \
cd ~/prod/maas-webapp; \
/usr/bin/env php bin/console --env=prod cache:clear; \
/usr/bin/env php bin/console --env=prod doctrine:database:create --if-not-exists --no-interaction; \
/usr/bin/env php bin/console --env=prod doctrine:migrations:migrate --no-interaction --allow-no-migration --all-or-nothing;
'
