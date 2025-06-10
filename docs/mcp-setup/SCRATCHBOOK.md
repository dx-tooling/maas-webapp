https://www.reddit.com/r/mcp/comments/1jr75bi/playwright_mcp_as_an_external_service/

https://github.com/microsoft/playwright-mcp


AWS Infra Account (herodot infra webapp prod): 230024871185


54.176.83.80

http://54.176.83.80/vnc.html

curl http://54.176.83.80/sse/10001 -H "Authorization: Bearer token1a"

10001 -> service ID -> MCP at port 8001

sudo apt update
sudo apt install xvfb
Xvfb :99 -screen 0 1024x768x24 &
export DISPLAY=:99


npx playwright install-deps
npx playwright install chromium
npx @playwright/mcp@latest --port 8931 --headless --browser chromium --isolated --no-sandbox --host 127.0.0.1





sudo apt install novnc websockify python3-websockify tigervnc-standalone-server

vncpasswd

mkdir -p ~/.vnc
nano ~/.vnc/xstartup

#!/bin/bash
unset SESSION_MANAGER
unset DBUS_SESSION_BUS_ADDRESS
[ -x /etc/vnc/xstartup ] && exec /etc/vnc/xstartup
[ -r $HOME/.Xresources ] && xrdb $HOME/.Xresources
vncconfig -iconic &
dbus-launch --exit-with-session xfce4-session &

chmod +x ~/.vnc/xstartup

x11vnc -display :99 -forever -shared -rfbport 5901 -rfbauth ~/.vnc/passwd

websockify --web=/usr/share/novnc/ 80 localhost:5901


