# Prod Setupbook

How do I get a prod environment for this application up and running?

# Infrastructure

- At domain provider, set `A` record for `mcp-as-a-service.com` to IPv4 address `152.53.168.103`, and `AAAA` record for `mcp-as-a-service.com` to IPv6 address `2a0a:4cc0:2000:ad21:0:0:0:0` (of netcup server v2202506282096352062).

# App Server System

    ssh root@v2202506282096352062.quicksrv.de
    apt update
    apt install software-properties-common
    add-apt-repository ppa:ondrej/php
    apt update
    apt install vim curl nginx certbot python3-certbot-nginx mariadb-server php8.4-cli php8.4-xml php8.4-fpm php8.4-mysql composer xvfb x11vnc novnc websockify python3-websockify tigervnc-standalone-server
    certbot --nginx -d mcp-as-a-service.com

Store files `/etc/letsencrypt/live/mcp-as-a-service.com/fullchain.pem` and `/etc/letsencrypt/live/mcp-as-a-service.com/privkey.pem`.
