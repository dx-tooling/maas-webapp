#!/usr/bin/env bash

sleep 5

cd /var/www/prod/maas-webapp

sudo -u www-data php bin/console --env=prod app:os-process-management:domain:generate-nginx-bearer-mappings /var/tmp/mcp-proxies.conf

mv /var/tmp/mcp-proxies.conf /etc/nginx/mcp-server-proxies.conf
nginx -t && service nginx restart
