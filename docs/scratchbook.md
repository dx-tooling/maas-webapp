https://www.reddit.com/r/mcp/comments/1jr75bi/playwright_mcp_as_an_external_service/

https://github.com/microsoft/playwright-mcp


docker run -it \
-v "$(pwd):/source" -v mcp-hdd:/mcp \
-p 127.0.0.1:11111:11111 \
-p 127.0.0.1:22222:22222 \
-p 127.0.0.1:33333:33333 \
-p 127.0.0.1:44444:44444 \
ubuntu:24.04 /bin/bash

AWS Infra Account (herodot infra webapp prod): 230024871185, us-west-1


54.176.83.80

http://54.176.83.80/vnc.html

curl http://54.176.83.80/sse/10001 -H "Authorization: Bearer token1a"

10001 -> service ID -> MCP at port 8001




apt update
apt install software-properties-common
add-apt-repository ppa:ondrej/php
apt update
apt install vim curl nginx certbot python3-certbot-nginx mariadb-server php8.4-cli php8.4-xml php8.4-fpm php8.4-mysql composer xvfb x11vnc novnc websockify python3-websockify tigervnc-standalone-server

# nginx einrichten
sudo certbot --nginx -d example.org

service mariadb start
mysql -uroot -e "CREATE DATABASE maas_webapp_prod;"
mysql -uroot -e "GRANT ALL PRIVILEGES ON maas_webapp_prod.* TO 'prod'@'localhost' IDENTIFIED BY 'Jiouejrf3483HXxbzbrf23';"

curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"  # This loads nvm bash_completion
nvm install 22
npm init
npm install @playwright/mcp@latest
npx playwright install-deps
npx playwright install chromium

php bin/console doctrine:migrations:migrate



echo "test123" | vncpasswd -f > test.pwd

mkdir -p ~/.vnc
vim ~/.vnc/xstartup

#!/bin/bash
unset SESSION_MANAGER
unset DBUS_SESSION_BUS_ADDRESS
[ -x /etc/vnc/xstartup ] && exec /etc/vnc/xstartup
[ -r $HOME/.Xresources ] && xrdb $HOME/.Xresources
vncconfig -iconic &
dbus-launch --exit-with-session xfce4-session &

chmod +x ~/.vnc/xstartup




# launch virtual frame buffer
Xvfb :99 -screen 0 1024x768x24

# launch playwright mcp using the vfb as its display
export DISPLAY=:99
npx @playwright/mcp@latest --port 11111 --headless --browser chromium --isolated --no-sandbox --host 0.0.0.0

# set up and launch the vnc server on the vfb display
echo "test123" | vncpasswd -f > /var/tmp/22222.pwd
x11vnc -display :99 -forever -shared -rfbport 22222 -rfbauth /var/tmp/22222.pwd

# make the vnc server reachable via a web client
websockify --web=/usr/share/novnc/ 33333 localhost:22222
