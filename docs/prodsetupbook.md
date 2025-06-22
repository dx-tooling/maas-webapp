# Prod Setupbook

How do I get a prod environment for this application up and running?

# Infrastructure

At domain provider, set the `A` record for `mcp-as-a-service.com` to IPv4 address `152.53.168.103`, and the `AAAA` record for `mcp-as-a-service.com` to IPv6 address `2a0a:4cc0:2000:ad21:0:0:0:0` (of Netcup server v2202506282096352062).

# App Server System

    ssh root@v2202506282096352062.quicksrv.de
    apt update
    apt install software-properties-common
    add-apt-repository ppa:ondrej/php
    apt update
    apt install vim curl nginx certbot python3-certbot-nginx mariadb-server php8.4-cli php8.4-mcrypt php8.4-xml php8.4-fpm php8.4-mysql composer xvfb x11vnc novnc websockify python3-websockify tigervnc-standalone-server
    certbot --nginx -d mcp-as-a-service.com

Store files `/etc/letsencrypt/live/mcp-as-a-service.com/fullchain.pem` and `/etc/letsencrypt/live/mcp-as-a-service.com/privkey.pem`.

    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
    export NVM_DIR="$HOME/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"  # This loads nvm bash_completion
    nvm install 22
    npx playwright install-deps
    echo "www-data ALL=(root) NOPASSWD: /var/www/prod/playwright-mcp-cloud-webapp/bin/generate-mcp-proxies.sh" > /etc/sudoers.d/100-www-data-mcp-proxy-gen
    touch /etc/nginx/mcp-server-proxies.conf
    rm /etc/nginx/sites-enabled/default

Set www-data login shell to `/bin/bash`.

    chown -R www-data:www-data /var/www
    sudo su - www-data
    mkdir -p /var/www/prod/mcp-env
    cd /var/www/prod/mcp-env
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
    export NVM_DIR="$HOME/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"  # This loads nvm bash_completion
    nvm install 22
    npm init
    npm install @playwright/mcp@latest
    npx playwright install chromium

Place [nginx.conf](infrastructure/etc/nginx/nginx.conf) at /etc/nginx/nginx.conf.

Place [webapp.conf](infrastructure/etc/nginx/sites-enabled/webapp.conf) at /etc/nginx/sites-enabled/webapp.conf.

Place [webapp.conf](infrastructure/etc/nginx/sites-enabled/mcp-server-proxy-site.conf) at /etc/nginx/sites-enabled/mcp-server-proxy-site.conf.

    nginx -T
    service nginx restart
