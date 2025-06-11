# Setting up a virtual display on Ubuntu
_Exported on 6/10/2025 at 15:24:30 GMT+2 from Cursor (1.0.0)_

---

**User**

What is currently the best way to set up a virtual display on an Ubuntu 24.04 server system (with no GPU etc.), in order to e.g. launch a Chromium browser that requires a DISPLAY?

---

**Cursor**

Let me help you with setting up a virtual display on Ubuntu 24.04. The most reliable and modern solution for this is using Xvfb (X Virtual Framebuffer) or Xvnc, with Xvfb being the lighter weight option. Here's the recommended approach:

1. First, install Xvfb:
```bash
sudo apt update
sudo apt install xvfb
```

2. You can start Xvfb in one of two ways:

Option 1 - Direct start:
```bash
Xvfb :99 -screen 0 1024x768x24 &
export DISPLAY=:99
```

Option 2 - Using xvfb-run (recommended for one-off commands):
```bash
xvfb-run --server-args="-screen 0 1024x768x24" your-command
```

For your specific case with Chromium, you would do:

```bash
# Install Chromium
sudo apt install chromium-browser

# Run Chromium with Xvfb
xvfb-run --server-args="-screen 0 1024x768x24" chromium-browser --no-sandbox
```

Some important notes:
1. The `--no-sandbox` flag is often needed when running Chromium in a headless environment
2. The screen resolution (1024x768x24) can be adjusted based on your needs
3. If you need to run multiple applications, the direct start method (Option 1) is better as it keeps the virtual display running

For a more permanent solution, you might want to create a systemd service to start Xvfb automatically. Here's how:

```bash
sudo nano /etc/systemd/system/xvfb.service
```

Add this content:
```ini
[Unit]
Description=X Virtual Framebuffer
After=network.target

[Service]
ExecStart=/usr/bin/Xvfb :99 -screen 0 1024x768x24
Restart=always

[Install]
WantedBy=multi-user.target
```

Then enable and start the service:
```bash
sudo systemctl enable xvfb
sudo systemctl start xvfb
```

After this, you can set the DISPLAY environment variable in your shell profile:
```bash
echo 'export DISPLAY=:99' >> ~/.bashrc
source ~/.bashrc
```

This setup will give you a reliable virtual display that you can use for any X11 application, including Chromium. The virtual display will be available at `:99` by default, but you can change this number if needed.

Remember that if you're running this in a Docker container, you might need to use a different approach or ensure that the container has the necessary permissions to create the virtual display.

```shellscript
sudo apt update
sudo apt install xvfb
```

```shellscript
Xvfb :99 -screen 0 1024x768x24 &
export DISPLAY=:99
```

```shellscript
xvfb-run --server-args="-screen 0 1024x768x24" your-command
```

```shellscript
# Install Chromium
sudo apt install chromium-browser

# Run Chromium with Xvfb
xvfb-run --server-args="-screen 0 1024x768x24" chromium-browser --no-sandbox
```

```shellscript
sudo nano /etc/systemd/system/xvfb.service
```

```ini
[Unit]
Description=X Virtual Framebuffer
After=network.target

[Service]
ExecStart=/usr/bin/Xvfb :99 -screen 0 1024x768x24
Restart=always

[Install]
WantedBy=multi-user.target
```

```shellscript
sudo systemctl enable xvfb
sudo systemctl start xvfb
```

```shellscript
echo 'export DISPLAY=:99' >> ~/.bashrc
source ~/.bashrc
```

---

**User**

Great, thanks. Assume that I want to watch a high-quality, low-latency video stream of the content of the virtual framebuffer in a remote web browser, over TCP/IP. How could I achieve this?

---

**Cursor**

For streaming the virtual framebuffer content to a web browser, there are several approaches, but I'll recommend using `noVNC` combined with `websockify` as it provides a good balance of performance, ease of use, and browser compatibility. Here's how to set it up:

1. First, install the required packages:
```bash
sudo apt update
sudo apt install novnc websockify python3-websockify
```

2. You'll need a VNC server to capture the Xvfb display. Let's use `tigervnc-standalone-server`:
```bash
sudo apt install tigervnc-standalone-server
```

3. Create a VNC password:
```bash
vncpasswd
```

4. Create a VNC server configuration file:
```bash
mkdir -p ~/.vnc
nano ~/.vnc/xstartup
```

Add this content to `xstartup`:
```bash
#!/bin/bash
unset SESSION_MANAGER
unset DBUS_SESSION_BUS_ADDRESS
[ -x /etc/vnc/xstartup ] && exec /etc/vnc/xstartup
[ -r $HOME/.Xresources ] && xrdb $HOME/.Xresources
vncconfig -iconic &
dbus-launch --exit-with-session xfce4-session &
```

Make it executable:
```bash
chmod +x ~/.vnc/xstartup
```

5. Start the VNC server on the virtual display:
```bash
vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth
```

6. Start noVNC with websockify:
```bash
websockify --web=/usr/share/novnc/ 8080 localhost:5901
```

Now you can access the virtual display by opening a web browser and navigating to:
```
http://your-server-ip:8080/vnc.html
```

For better performance and lower latency, you can adjust some parameters:

1. Modify the VNC server startup command with additional options:
```bash
vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth -rfbport 5901 -FrameRate 30 -QualityLevel 9
```

2. You can also create a systemd service for automatic startup. Create `/etc/systemd/system/vncserver.service`:
```ini
[Unit]
Description=VNC Server
After=network.target xvfb.service

[Service]
Type=forking
User=your-username
WorkingDirectory=/home/your-username
ExecStart=/usr/bin/vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth -rfbport 5901 -FrameRate 30 -QualityLevel 9
ExecStop=/usr/bin/vncserver -kill :1
Restart=always

[Install]
WantedBy=multi-user.target
```

And create `/etc/systemd/system/novnc.service`:
```ini
[Unit]
Description=noVNC Server
After=network.target vncserver.service

[Service]
Type=simple
User=your-username
WorkingDirectory=/home/your-username
ExecStart=/usr/bin/websockify --web=/usr/share/novnc/ 8080 localhost:5901
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start the services:
```bash
sudo systemctl enable vncserver
sudo systemctl enable novnc
sudo systemctl start vncserver
sudo systemctl start novnc
```

For even better performance, you can consider these alternatives:

1. **Apache Guacamole**: A more feature-rich solution that supports multiple protocols (VNC, RDP, SSH) and provides a better web interface.

2. **x11vnc**: An alternative VNC server that might provide better performance in some cases:
```bash
sudo apt install x11vnc
x11vnc -display :99 -forever -shared -rfbport 5901
```

3. **TurboVNC**: A high-performance VNC server:
```bash
sudo apt install turbovnc
```

For security considerations:
1. Always use HTTPS if exposing the service to the internet
2. Consider using a reverse proxy like Nginx
3. Implement proper authentication
4. Use firewall rules to restrict access

If you need to stream to multiple clients simultaneously, the shared VNC server setup above will work, but you might want to consider using a more robust solution like Guacamole or a custom WebRTC-based solution for better performance with multiple viewers.

```shellscript
sudo apt update
sudo apt install novnc websockify python3-websockify
```

```shellscript
sudo apt install tigervnc-standalone-server
```

```shellscript
vncpasswd
```

```shellscript
mkdir -p ~/.vnc
nano ~/.vnc/xstartup
```

```shellscript
#!/bin/bash
unset SESSION_MANAGER
unset DBUS_SESSION_BUS_ADDRESS
[ -x /etc/vnc/xstartup ] && exec /etc/vnc/xstartup
[ -r $HOME/.Xresources ] && xrdb $HOME/.Xresources
vncconfig -iconic &
dbus-launch --exit-with-session xfce4-session &
```

```shellscript
chmod +x ~/.vnc/xstartup
```

```shellscript
vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth
```

```shellscript
websockify --web=/usr/share/novnc/ 8080 localhost:5901
```

```plaintext
http://your-server-ip:8080/vnc.html
```

```shellscript
vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth -rfbport 5901 -FrameRate 30 -QualityLevel 9
```

```ini
[Unit]
Description=VNC Server
After=network.target xvfb.service

[Service]
Type=forking
User=your-username
WorkingDirectory=/home/your-username
ExecStart=/usr/bin/vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth -rfbport 5901 -FrameRate 30 -QualityLevel 9
ExecStop=/usr/bin/vncserver -kill :1
Restart=always

[Install]
WantedBy=multi-user.target
```

```ini
[Unit]
Description=noVNC Server
After=network.target vncserver.service

[Service]
Type=simple
User=your-username
WorkingDirectory=/home/your-username
ExecStart=/usr/bin/websockify --web=/usr/share/novnc/ 8080 localhost:5901
Restart=always

[Install]
WantedBy=multi-user.target
```

```shellscript
sudo systemctl enable vncserver
sudo systemctl enable novnc
sudo systemctl start vncserver
sudo systemctl start novnc
```

```shellscript
sudo apt install x11vnc
x11vnc -display :99 -forever -shared -rfbport 5901
```

```shellscript
sudo apt install turbovnc
```

---

**User**

mh, i started Xvfb on :99, do I now need to launch vncserver like this?

vncserver :99 -geometry 1920x1080 -depth 24 -localhost no -SecurityTypes VncAuth

Because that results in "A X11 server is already running for display :99 on machine ip-172-31-23-182.us-west-1.compute.internal"

---

**Cursor**

Ah, I see the issue. When you're using Xvfb on `:99`, you don't need to start a new VNC server on the same display number. Instead, you should use `x11vnc` which can connect to an existing X display. Here's the correct approach:

1. First, make sure Xvfb is running on `:99`:
```bash
Xvfb :99 -screen 0 1920x1080x24 &
export DISPLAY=:99
```

2. Then, use `x11vnc` to connect to that existing display:
```bash
x11vnc -display :99 -forever -shared -rfbport 5901
```

This is actually a better approach because:
1. It doesn't try to create a new X server
2. It directly connects to your existing Xvfb display
3. It's more efficient as it doesn't need to manage its own X server

You can then connect noVNC to this VNC server as before:
```bash
websockify --web=/usr/share/novnc/ 8080 localhost:5901
```

If you want to combine these into a single command for testing, you can do:
```bash
Xvfb :99 -screen 0 1920x1080x24 & export DISPLAY=:99 && x11vnc -display :99 -forever -shared -rfbport 5901
```

This should work without the "X11 server already running" error you encountered.

```shellscript
Xvfb :99 -screen 0 1920x1080x24 &
export DISPLAY=:99
```

```shellscript
x11vnc -display :99 -forever -shared -rfbport 5901
```

```shellscript
websockify --web=/usr/share/novnc/ 8080 localhost:5901
```

```shellscript
Xvfb :99 -screen 0 1920x1080x24 & export DISPLAY=:99 && x11vnc -display :99 -forever -shared -rfbport 5901
```

---

**User**

Works beautifully, but I'm not asked for a password when opening the novnc webpage in my browser.

---

**Cursor**

Ah yes, we should add password protection to the VNC server. With `x11vnc`, you can set a password in two ways:

1. **Interactive password setup** (recommended for first-time setup):
```bash
x11vnc -display :99 -forever -shared -rfbport 5901 -storepasswd
```
This will prompt you to enter and confirm a password, and store it in `~/.vnc/passwd`.

2. **Using the stored password**:
```bash
x11vnc -display :99 -forever -shared -rfbport 5901 -rfbauth ~/.vnc/passwd
```

So the complete sequence would be:

```bash
# First time setup - create password
x11vnc -storepasswd

# Then run the server with password protection
x11vfb :99 -screen 0 1920x1080x24 & export DISPLAY=:99 && x11vnc -display :99 -forever -shared -rfbport 5901 -rfbauth ~/.vnc/passwd
```

Now when you connect through the noVNC webpage, you should be prompted for the password you set.

If you want to change the password later, you can either:
1. Delete the existing `~/.vnc/passwd` file and run `x11vnc -storepasswd` again
2. Or use the `-storepasswd` option with a new password directly:
```bash
x11vnc -storepasswd new_password
```

This will ensure your virtual display is properly secured and only accessible to users with the correct password.

```shellscript
x11vnc -display :99 -forever -shared -rfbport 5901 -storepasswd
```

```shellscript
x11vnc -display :99 -forever -shared -rfbport 5901 -rfbauth ~/.vnc/passwd
```

```shellscript
# First time setup - create password
x11vnc -storepasswd

# Then run the server with password protection
x11vfb :99 -screen 0 1920x1080x24 & export DISPLAY=:99 && x11vnc -display :99 -forever -shared -rfbport 5901 -rfbauth ~/.vnc/passwd
```

```shellscript
x11vnc -storepasswd new_password
```

---

**User**

Awesome.

No another topic. On the same server, I am running a service that binds to a given port on localhost, and provides HTTP on this port, with SSE (server-side events).

I need to run an HTTP proxy in front of this service, allowing me to enforce "Bearer Auth" authentication for HTTP clients connecting to this service (the service itself does not provide any auth means).

---

**Cursor**

For this use case, I'd recommend using Nginx as a reverse proxy with Bearer token authentication. It's lightweight, performant, and well-suited for handling HTTP/SSE connections. Here's how to set it up:

1. First, install Nginx:
```bash
sudo apt update
sudo apt install nginx
```

2. Create a new Nginx configuration file for your service:
```bash
sudo nano /etc/nginx/sites-available/sse-proxy
```

Here's a configuration that handles both regular HTTP and SSE connections with Bearer token authentication:

```nginx
# Define the upstream server (your local service)
upstream sse_backend {
    server 127.0.0.1:YOUR_PORT;  # Replace YOUR_PORT with your service's port
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Define valid tokens (you can add more)
map $auth_token $is_valid {
    default 0;
    "your-secret-token-here" 1;  # Replace with your actual token
}

server {
    listen 80;
    # If you want HTTPS (recommended), uncomment these lines:
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    server_name your-domain.com;  # Replace with your domain or IP

    # Authentication check
    if ($is_valid = 0) {
        return 401;
    }

    location / {
        proxy_pass http://sse_backend;
        
        # Standard proxy settings
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # SSE specific settings
        proxy_set_header Connection '';
        proxy_http_version 1.1;
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 86400s;  # 24 hours
        proxy_send_timeout 86400s;  # 24 hours

        # CORS settings if needed
        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
        add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
        add_header 'Access-Control-Expose-Headers' 'Content-Length,Content-Range';
    }
}
```

3. Enable the site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/sse-proxy /etc/nginx/sites-enabled/
sudo nginx -t  # Test the configuration
sudo systemctl restart nginx
```

4. If you want to use multiple tokens, you can modify the `map` directive to include more tokens:

```nginx
map $auth_token $is_valid {
    default 0;
    "token1" 1;
    "token2" 1;
    "token3" 1;
}
```

5. For better security, you might want to store tokens in a separate file:

```bash
sudo nano /etc/nginx/tokens.conf
```

Add your tokens:
```nginx
map $auth_token $is_valid {
    default 0;
    include /etc/nginx/tokens.conf;
}
```

Then in `/etc/nginx/tokens.conf`:
```nginx
"token1" 1;
"token2" 1;
"token3" 1;
```

To use the proxy, clients would need to include the Bearer token in their requests:

```javascript
// Example client code
fetch('http://your-server/', {
    headers: {
        'Authorization': 'Bearer your-secret-token-here'
    }
});
```

For SSE connections:
```javascript
const eventSource = new EventSource('http://your-server/events', {
    headers: {
        'Authorization': 'Bearer your-secret-token-here'
    }
});
```

Additional security considerations:

1. **HTTPS**: If this is exposed to the internet, definitely use HTTPS. You can get free certificates from Let's Encrypt.

2. **Rate Limiting**: Add rate limiting to prevent abuse:
```nginx
# Add to http block
limit_req_zone $binary_remote_addr zone=one:10m rate=1r/s;

# Add to server block
location / {
    limit_req zone=one burst=10 nodelay;
    # ... rest of your config
}
```

3. **IP Restrictions**: If you know the IP ranges that should access the service:
```nginx
location / {
    allow 192.168.1.0/24;  # Allow specific IP range
    deny all;              # Deny all others
    # ... rest of your config
}
```

4. **Token Rotation**: Consider implementing a system to rotate tokens periodically.

5. **Logging**: Add detailed logging for security monitoring:
```nginx
log_format detailed '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    '"$http_authorization"';

access_log /var/log/nginx/sse-proxy.log detailed;
```

This setup provides a secure way to proxy your SSE service while adding Bearer token authentication. The configuration is also optimized for long-lived SSE connections.

```shellscript
sudo apt update
sudo apt install nginx
```

```shellscript
sudo nano /etc/nginx/sites-available/sse-proxy
```

```plaintext
# Define the upstream server (your local service)
upstream sse_backend {
    server 127.0.0.1:YOUR_PORT;  # Replace YOUR_PORT with your service's port
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Define valid tokens (you can add more)
map $auth_token $is_valid {
    default 0;
    "your-secret-token-here" 1;  # Replace with your actual token
}

server {
    listen 80;
    # If you want HTTPS (recommended), uncomment these lines:
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    server_name your-domain.com;  # Replace with your domain or IP

    # Authentication check
    if ($is_valid = 0) {
        return 401;
    }

    location / {
        proxy_pass http://sse_backend;
        
        # Standard proxy settings
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # SSE specific settings
        proxy_set_header Connection '';
        proxy_http_version 1.1;
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 86400s;  # 24 hours
        proxy_send_timeout 86400s;  # 24 hours

        # CORS settings if needed
        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
        add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
        add_header 'Access-Control-Expose-Headers' 'Content-Length,Content-Range';
    }
}
```

```shellscript
sudo ln -s /etc/nginx/sites-available/sse-proxy /etc/nginx/sites-enabled/
sudo nginx -t  # Test the configuration
sudo systemctl restart nginx
```

```plaintext
map $auth_token $is_valid {
    default 0;
    "token1" 1;
    "token2" 1;
    "token3" 1;
}
```

```shellscript
sudo nano /etc/nginx/tokens.conf
```

```plaintext
map $auth_token $is_valid {
    default 0;
    include /etc/nginx/tokens.conf;
}
```

```plaintext
"token1" 1;
"token2" 1;
"token3" 1;
```

```javascript
// Example client code
fetch('http://your-server/', {
    headers: {
        'Authorization': 'Bearer your-secret-token-here'
    }
});
```

```javascript
const eventSource = new EventSource('http://your-server/events', {
    headers: {
        'Authorization': 'Bearer your-secret-token-here'
    }
});
```

```plaintext
# Add to http block
limit_req_zone $binary_remote_addr zone=one:10m rate=1r/s;

# Add to server block
location / {
    limit_req zone=one burst=10 nodelay;
    # ... rest of your config
}
```

```plaintext
location / {
    allow 192.168.1.0/24;  # Allow specific IP range
    deny all;              # Deny all others
    # ... rest of your config
}
```

```plaintext
log_format detailed '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    '"$http_authorization"';

access_log /var/log/nginx/sse-proxy.log detailed;
```

---

**User**

Oh, you know what would be even better? If I could use this reverse proxy to serve multiple of these SSE http instances under one host, so that http://example.com/1234/sse proxies through to a service instance listening on port A, and http://example.com/1234/sse proxies through to a service instance listening on port B.


---

**Cursor**

Yes, absolutely! We can modify the Nginx configuration to handle multiple SSE services under different paths. Here's how to set it up:

```nginx
# Define the upstream servers
upstream sse_backend_1234 {
    server 127.0.0.1:PORT_A;  # Replace with your first service's port
}

upstream sse_backend_5678 {
    server 127.0.0.1:PORT_B;  # Replace with your second service's port
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Define valid tokens
map $auth_token $is_valid {
    default 0;
    "your-secret-token-here" 1;  # Replace with your actual token
}

server {
    listen 80;
    # If you want HTTPS (recommended), uncomment these lines:
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    server_name your-domain.com;  # Replace with your domain or IP

    # Authentication check
    if ($is_valid = 0) {
        return 401;
    }

    # Common proxy settings
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;

    # CORS settings
    add_header 'Access-Control-Allow-Origin' '*';
    add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
    add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
    add_header 'Access-Control-Expose-Headers' 'Content-Length,Content-Range';

    # First SSE service
    location /1234/sse {
        proxy_pass http://sse_backend_1234;
    }

    # Second SSE service
    location /5678/sse {
        proxy_pass http://sse_backend_5678;
    }

    # Optional: Add a health check endpoint
    location /health {
        return 200 'OK';
        add_header Content-Type text/plain;
    }
}
```

For a more dynamic approach where you might have many services, you could use a more flexible configuration:

```nginx
# Define a map to route paths to ports
map $request_uri $backend_port {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the upstream server dynamically
upstream sse_backend {
    server 127.0.0.1:$backend_port;
}

server {
    # ... (previous server settings remain the same)

    # Dynamic SSE routing
    location ~ ^/sse/(\d+)(/.*)?$ {
        # Extract the port number from the URL
        set $port $1;
        
        # Validate port number (optional)
        if ($port !~ "^[0-9]+$") {
            return 400;
        }
        
        # Proxy to the correct backend
        proxy_pass http://127.0.0.1:$port$2;
    }
}
```

With this dynamic configuration, you could access your services like:
- `http://example.com/sse/1234/` → routes to port 1234
- `http://example.com/sse/5678/` → routes to port 5678

For even more flexibility, you could use a database or file-based configuration to manage the port mappings. Here's an example using a JSON file:

```bash
sudo nano /etc/nginx/sse-mappings.json
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"]
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"]
    }
}
```

Then modify the Nginx configuration to use this mapping:

```nginx
# Load the mappings
map $request_uri $backend_port {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Check if token is valid for this service
map $auth_token$backend_port $is_valid {
    default 0;
    include /etc/nginx/sse-mappings.json;
}

server {
    # ... (previous server settings)

    location ~ ^/sse/(\d+)(/.*)?$ {
        # Authentication check
        if ($is_valid = 0) {
            return 401;
        }

        # Get the port from the mapping
        set $port $backend_port;
        
        # Proxy to the correct backend
        proxy_pass http://127.0.0.1:$port$2;
    }
}
```

This setup allows you to:
1. Route different SSE services under different paths
2. Maintain separate authentication tokens for each service
3. Easily add or remove services by updating the configuration
4. Keep the configuration organized and maintainable

Remember to:
1. Adjust the timeouts and buffer settings based on your needs
2. Implement proper logging for each service
3. Consider adding rate limiting per service
4. Use HTTPS in production
5. Regularly rotate tokens
6. Monitor the connections and server resources

You can test the setup using curl or a browser:

```bash
# Test first service
curl -H "Authorization: Bearer token1" http://example.com/sse/1234/

# Test second service
curl -H "Authorization: Bearer token3" http://example.com/sse/5678/
```

Or with JavaScript:

```javascript
// Connect to first service
const eventSource1 = new EventSource('http://example.com/sse/1234/', {
    headers: {
        'Authorization': 'Bearer token1'
    }
});

// Connect to second service
const eventSource2 = new EventSource('http://example.com/sse/5678/', {
    headers: {
        'Authorization': 'Bearer token3'
    }
});
```

```plaintext
# Define the upstream servers
upstream sse_backend_1234 {
    server 127.0.0.1:PORT_A;  # Replace with your first service's port
}

upstream sse_backend_5678 {
    server 127.0.0.1:PORT_B;  # Replace with your second service's port
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Define valid tokens
map $auth_token $is_valid {
    default 0;
    "your-secret-token-here" 1;  # Replace with your actual token
}

server {
    listen 80;
    # If you want HTTPS (recommended), uncomment these lines:
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    server_name your-domain.com;  # Replace with your domain or IP

    # Authentication check
    if ($is_valid = 0) {
        return 401;
    }

    # Common proxy settings
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;

    # CORS settings
    add_header 'Access-Control-Allow-Origin' '*';
    add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
    add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
    add_header 'Access-Control-Expose-Headers' 'Content-Length,Content-Range';

    # First SSE service
    location /1234/sse {
        proxy_pass http://sse_backend_1234;
    }

    # Second SSE service
    location /5678/sse {
        proxy_pass http://sse_backend_5678;
    }

    # Optional: Add a health check endpoint
    location /health {
        return 200 'OK';
        add_header Content-Type text/plain;
    }
}
```

```plaintext
# Define a map to route paths to ports
map $request_uri $backend_port {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the upstream server dynamically
upstream sse_backend {
    server 127.0.0.1:$backend_port;
}

server {
    # ... (previous server settings remain the same)

    # Dynamic SSE routing
    location ~ ^/sse/(\d+)(/.*)?$ {
        # Extract the port number from the URL
        set $port $1;
        
        # Validate port number (optional)
        if ($port !~ "^[0-9]+$") {
            return 400;
        }
        
        # Proxy to the correct backend
        proxy_pass http://127.0.0.1:$port$2;
    }
}
```

```shellscript
sudo nano /etc/nginx/sse-mappings.json
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"]
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"]
    }
}
```

```plaintext
# Load the mappings
map $request_uri $backend_port {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Check if token is valid for this service
map $auth_token$backend_port $is_valid {
    default 0;
    include /etc/nginx/sse-mappings.json;
}

server {
    # ... (previous server settings)

    location ~ ^/sse/(\d+)(/.*)?$ {
        # Authentication check
        if ($is_valid = 0) {
            return 401;
        }

        # Get the port from the mapping
        set $port $backend_port;
        
        # Proxy to the correct backend
        proxy_pass http://127.0.0.1:$port$2;
    }
}
```

```shellscript
# Test first service
curl -H "Authorization: Bearer token1" http://example.com/sse/1234/

# Test second service
curl -H "Authorization: Bearer token3" http://example.com/sse/5678/
```

```javascript
// Connect to first service
const eventSource1 = new EventSource('http://example.com/sse/1234/', {
    headers: {
        'Authorization': 'Bearer token1'
    }
});

// Connect to second service
const eventSource2 = new EventSource('http://example.com/sse/5678/', {
    headers: {
        'Authorization': 'Bearer token3'
    }
});
```

---

**User**

Yes, the dynamic JSON approach is the way to go. Please give me the full nginx config file(s) rundown for that!

---

**Cursor**

I'll provide a complete setup with multiple configuration files for better organization. Here's the full rundown:

1. First, create the JSON mappings file:
```bash
sudo nano /etc/nginx/sse-mappings.json
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"],
        "description": "Service A"
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"],
        "description": "Service B"
    }
}
```

2. Create a main Nginx configuration file:
```bash
sudo nano /etc/nginx/sites-available/sse-proxy
```

```nginx
# Load the mappings
map $request_uri $service_id {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Check if token is valid for this service
map $auth_token$service_id $is_valid {
    default 0;
    include /etc/nginx/sse-mappings.json;
}

# Get the port for the service
map $service_id $backend_port {
    default "";
    include /etc/nginx/sse-mappings.json;
}

# Rate limiting zones
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=sse:10m rate=5r/s;

# Logging format
log_format detailed '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    '"$http_authorization" '
                    'service_id=$service_id';

server {
    listen 80;
    # If using HTTPS (recommended), uncomment these lines:
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    server_name your-domain.com;  # Replace with your domain or IP

    # Access and error logs
    access_log /var/log/nginx/sse-proxy.access.log detailed;
    error_log /var/log/nginx/sse-proxy.error.log;

    # Common proxy settings
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;

    # CORS settings
    add_header 'Access-Control-Allow-Origin' '*';
    add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
    add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
    add_header 'Access-Control-Expose-Headers' 'Content-Length,Content-Range';

    # Health check endpoint
    location /health {
        limit_req zone=general burst=20 nodelay;
        return 200 'OK';
        add_header Content-Type text/plain;
    }

    # Service status endpoint (optional)
    location /status {
        limit_req zone=general burst=20 nodelay;
        stub_status on;
        access_log off;
        allow 127.0.0.1;  # Only allow local access
        deny all;
    }

    # Dynamic SSE routing
    location ~ ^/sse/(\d+)(/.*)?$ {
        # Rate limiting
        limit_req zone=sse burst=10 nodelay;

        # Validate service ID
        if ($service_id = "") {
            return 400 "Invalid service ID";
        }

        # Validate port
        if ($backend_port = "") {
            return 404 "Service not found";
        }

        # Authentication check
        if ($is_valid = 0) {
            return 401 "Unauthorized";
        }

        # Proxy to the correct backend
        proxy_pass http://127.0.0.1:$backend_port$2;
    }

    # Default error page
    error_page 400 401 403 404 500 502 503 504 /error.html;
    location = /error.html {
        internal;
        return 200 '{"error": "$status", "message": "$status_text"}';
        add_header Content-Type application/json;
    }
}
```

3. Create a script to validate the JSON configuration:
```bash
sudo nano /etc/nginx/validate-sse-config.sh
```

```bash
#!/bin/bash

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "jq is not installed. Installing..."
    sudo apt-get update && sudo apt-get install -y jq
fi

# Validate JSON syntax
if ! jq '.' /etc/nginx/sse-mappings.json > /dev/null 2>&1; then
    echo "Error: Invalid JSON syntax in sse-mappings.json"
    exit 1
fi

# Validate required fields
jq -r 'to_entries[] | select(.value.port == null or .value.allowed_tokens == null) | .key' /etc/nginx/sse-mappings.json | while read -r service_id; do
    if [ ! -z "$service_id" ]; then
        echo "Error: Service $service_id is missing required fields (port or allowed_tokens)"
        exit 1
    fi
done

echo "Configuration is valid!"
exit 0
```

Make it executable:
```bash
sudo chmod +x /etc/nginx/validate-sse-config.sh
```

4. Create a script to reload the configuration:
```bash
sudo nano /etc/nginx/reload-sse-config.sh
```

```bash
#!/bin/bash

# Validate the configuration
/etc/nginx/validate-sse-config.sh
if [ $? -ne 0 ]; then
    echo "Configuration validation failed. Aborting reload."
    exit 1
fi

# Test Nginx configuration
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "Nginx configuration test failed. Aborting reload."
    exit 1
fi

# Reload Nginx
sudo systemctl reload nginx
echo "Configuration reloaded successfully!"
```

Make it executable:
```bash
sudo chmod +x /etc/nginx/reload-sse-config.sh
```

5. Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/sse-proxy /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

To use this setup:

1. To add a new service, edit `/etc/nginx/sse-mappings.json`:
```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"],
        "description": "Service A"
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"],
        "description": "Service B"
    },
    "9012": {
        "port": 8003,
        "allowed_tokens": ["token5", "token6"],
        "description": "Service C"
    }
}
```

2. After making changes, reload the configuration:
```bash
sudo /etc/nginx/reload-sse-config.sh
```

3. Test the endpoints:
```bash
# Test health check
curl http://your-domain.com/health

# Test SSE connection
curl -H "Authorization: Bearer token1" http://your-domain.com/sse/1234/

# Test invalid service
curl -H "Authorization: Bearer token1" http://your-domain.com/sse/9999/

# Test invalid token
curl -H "Authorization: Bearer invalid-token" http://your-domain.com/sse/1234/
```

This setup provides:
- Dynamic service routing based on JSON configuration
- Token-based authentication per service
- Rate limiting
- Detailed logging
- Health checks
- Error handling
- Configuration validation
- Easy service management

Remember to:
1. Replace `your-domain.com` with your actual domain
2. Set up HTTPS in production
3. Adjust rate limits based on your needs
4. Regularly rotate tokens
5. Monitor the logs for any issues
6. Back up the configuration files

```shellscript
sudo nano /etc/nginx/sse-mappings.json
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"],
        "description": "Service A"
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"],
        "description": "Service B"
    }
}
```

```shellscript
sudo nano /etc/nginx/sites-available/sse-proxy
```

```plaintext
# Load the mappings
map $request_uri $service_id {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Check if token is valid for this service
map $auth_token$service_id $is_valid {
    default 0;
    include /etc/nginx/sse-mappings.json;
}

# Get the port for the service
map $service_id $backend_port {
    default "";
    include /etc/nginx/sse-mappings.json;
}

# Rate limiting zones
limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=sse:10m rate=5r/s;

# Logging format
log_format detailed '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    '"$http_authorization" '
                    'service_id=$service_id';

server {
    listen 80;
    # If using HTTPS (recommended), uncomment these lines:
    # listen 443 ssl;
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    server_name your-domain.com;  # Replace with your domain or IP

    # Access and error logs
    access_log /var/log/nginx/sse-proxy.access.log detailed;
    error_log /var/log/nginx/sse-proxy.error.log;

    # Common proxy settings
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;

    # CORS settings
    add_header 'Access-Control-Allow-Origin' '*';
    add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
    add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';
    add_header 'Access-Control-Expose-Headers' 'Content-Length,Content-Range';

    # Health check endpoint
    location /health {
        limit_req zone=general burst=20 nodelay;
        return 200 'OK';
        add_header Content-Type text/plain;
    }

    # Service status endpoint (optional)
    location /status {
        limit_req zone=general burst=20 nodelay;
        stub_status on;
        access_log off;
        allow 127.0.0.1;  # Only allow local access
        deny all;
    }

    # Dynamic SSE routing
    location ~ ^/sse/(\d+)(/.*)?$ {
        # Rate limiting
        limit_req zone=sse burst=10 nodelay;

        # Validate service ID
        if ($service_id = "") {
            return 400 "Invalid service ID";
        }

        # Validate port
        if ($backend_port = "") {
            return 404 "Service not found";
        }

        # Authentication check
        if ($is_valid = 0) {
            return 401 "Unauthorized";
        }

        # Proxy to the correct backend
        proxy_pass http://127.0.0.1:$backend_port$2;
    }

    # Default error page
    error_page 400 401 403 404 500 502 503 504 /error.html;
    location = /error.html {
        internal;
        return 200 '{"error": "$status", "message": "$status_text"}';
        add_header Content-Type application/json;
    }
}
```

```shellscript
sudo nano /etc/nginx/validate-sse-config.sh
```

```shellscript
#!/bin/bash

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "jq is not installed. Installing..."
    sudo apt-get update && sudo apt-get install -y jq
fi

# Validate JSON syntax
if ! jq '.' /etc/nginx/sse-mappings.json > /dev/null 2>&1; then
    echo "Error: Invalid JSON syntax in sse-mappings.json"
    exit 1
fi

# Validate required fields
jq -r 'to_entries[] | select(.value.port == null or .value.allowed_tokens == null) | .key' /etc/nginx/sse-mappings.json | while read -r service_id; do
    if [ ! -z "$service_id" ]; then
        echo "Error: Service $service_id is missing required fields (port or allowed_tokens)"
        exit 1
    fi
done

echo "Configuration is valid!"
exit 0
```

```shellscript
sudo chmod +x /etc/nginx/validate-sse-config.sh
```

```shellscript
sudo nano /etc/nginx/reload-sse-config.sh
```

```shellscript
#!/bin/bash

# Validate the configuration
/etc/nginx/validate-sse-config.sh
if [ $? -ne 0 ]; then
    echo "Configuration validation failed. Aborting reload."
    exit 1
fi

# Test Nginx configuration
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "Nginx configuration test failed. Aborting reload."
    exit 1
fi

# Reload Nginx
sudo systemctl reload nginx
echo "Configuration reloaded successfully!"
```

```shellscript
sudo chmod +x /etc/nginx/reload-sse-config.sh
```

```shellscript
sudo ln -s /etc/nginx/sites-available/sse-proxy /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"],
        "description": "Service A"
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"],
        "description": "Service B"
    },
    "9012": {
        "port": 8003,
        "allowed_tokens": ["token5", "token6"],
        "description": "Service C"
    }
}
```

```shellscript
sudo /etc/nginx/reload-sse-config.sh
```

```shellscript
# Test health check
curl http://your-domain.com/health

# Test SSE connection
curl -H "Authorization: Bearer token1" http://your-domain.com/sse/1234/

# Test invalid service
curl -H "Authorization: Bearer token1" http://your-domain.com/sse/9999/

# Test invalid token
curl -H "Authorization: Bearer invalid-token" http://your-domain.com/sse/1234/
```

---

**User**

$ sudo nginx -t
2025/06/10 13:22:55 [emerg] 20698#20698: unexpected "{" in /etc/nginx/playwright-mcp-mappings.json:1
nginx: configuration file /etc/nginx/nginx.conf test failed

---

**Cursor**

Ah, I see the issue. The problem is that Nginx's `map` directive can't directly include JSON files. We need to modify our approach to use a format that Nginx can understand. Let's create two files:

1. First, create a JSON file for your service mappings:
```bash
sudo nano /etc/nginx/sse-mappings.json
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"],
        "description": "Service A"
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"],
        "description": "Service B"
    }
}
```

2. Then, create a Nginx-compatible map file:
```bash
sudo nano /etc/nginx/sse-mappings.conf
```

```nginx
# Port mappings
map $service_id $backend_port {
    default "";
    1234 8001;
    5678 8002;
}

# Token validation mappings
map $auth_token$service_id $is_valid {
    default 0;
    # Service 1234 tokens
    "token1"1234 1;
    "token2"1234 1;
    # Service 5678 tokens
    "token3"5678 1;
    "token4"5678 1;
}
```

3. Update the main Nginx configuration to use the `.conf` file instead of the JSON:
```bash
sudo nano /etc/nginx/sites-available/sse-proxy
```

```nginx
# Load the mappings
map $request_uri $service_id {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Include the mappings
include /etc/nginx/sse-mappings.conf;

# Rest of your configuration remains the same...
```

4. Create a script to generate the Nginx map file from the JSON:
```bash
sudo nano /etc/nginx/generate-maps.sh
```

```bash
#!/bin/bash

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "jq is not installed. Installing..."
    sudo apt-get update && sudo apt-get install -y jq
fi

# Create the port mappings
echo "# Port mappings" > /etc/nginx/sse-mappings.conf
echo "map \$service_id \$backend_port {" >> /etc/nginx/sse-mappings.conf
echo "    default \"\";" >> /etc/nginx/sse-mappings.conf

# Add port mappings
jq -r 'to_entries[] | "    \(.key) \(.value.port);"' /etc/nginx/sse-mappings.json >> /etc/nginx/sse-mappings.conf

echo "}" >> /etc/nginx/sse-mappings.conf
echo "" >> /etc/nginx/sse-mappings.conf

# Create the token validation mappings
echo "# Token validation mappings" >> /etc/nginx/sse-mappings.conf
echo "map \$auth_token\$service_id \$is_valid {" >> /etc/nginx/sse-mappings.conf
echo "    default 0;" >> /etc/nginx/sse-mappings.conf

# Add token mappings
jq -r 'to_entries[] | .value.allowed_tokens[] as $token | "    \"\($token)\"\(.key) 1;"' /etc/nginx/sse-mappings.json >> /etc/nginx/sse-mappings.conf

echo "}" >> /etc/nginx/sse-mappings.conf

# Test the Nginx configuration
nginx -t

if [ $? -eq 0 ]; then
    echo "Configuration generated successfully!"
else
    echo "Error in generated configuration!"
    exit 1
fi
```

Make it executable:
```bash
sudo chmod +x /etc/nginx/generate-maps.sh
```

5. Update the reload script to use the new generator:
```bash
sudo nano /etc/nginx/reload-sse-config.sh
```

```bash
#!/bin/bash

# Generate the Nginx maps
sudo /etc/nginx/generate-maps.sh
if [ $? -ne 0 ]; then
    echo "Failed to generate Nginx maps. Aborting reload."
    exit 1
fi

# Test Nginx configuration
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "Nginx configuration test failed. Aborting reload."
    exit 1
fi

# Reload Nginx
sudo systemctl reload nginx
echo "Configuration reloaded successfully!"
```

Now when you want to add or modify services:

1. Edit the JSON file (`/etc/nginx/sse-mappings.json`)
2. Run the generator script:
```bash
sudo /etc/nginx/generate-maps.sh
```
3. Reload Nginx:
```bash
sudo /etc/nginx/reload-sse-config.sh
```

This approach gives you the best of both worlds:
- Easy-to-edit JSON configuration for service management
- Nginx-compatible map files for actual configuration
- Automated conversion between the two formats
- Validation at each step

The error you encountered should now be resolved since we're using proper Nginx map syntax in the generated configuration file.

```shellscript
sudo nano /etc/nginx/sse-mappings.json
```

```json
{
    "1234": {
        "port": 8001,
        "allowed_tokens": ["token1", "token2"],
        "description": "Service A"
    },
    "5678": {
        "port": 8002,
        "allowed_tokens": ["token3", "token4"],
        "description": "Service B"
    }
}
```

```shellscript
sudo nano /etc/nginx/sse-mappings.conf
```

```plaintext
# Port mappings
map $service_id $backend_port {
    default "";
    1234 8001;
    5678 8002;
}

# Token validation mappings
map $auth_token$service_id $is_valid {
    default 0;
    # Service 1234 tokens
    "token1"1234 1;
    "token2"1234 1;
    # Service 5678 tokens
    "token3"5678 1;
    "token4"5678 1;
}
```

```shellscript
sudo nano /etc/nginx/sites-available/sse-proxy
```

```plaintext
# Load the mappings
map $request_uri $service_id {
    default "";
    "~^/sse/(\d+)" $1;
}

# Define the auth token
map $http_authorization $auth_token {
    default "";
    "~^Bearer\s+(.*)$" $1;
}

# Include the mappings
include /etc/nginx/sse-mappings.conf;

# Rest of your configuration remains the same...
```

```shellscript
sudo nano /etc/nginx/generate-maps.sh
```

```shellscript
#!/bin/bash

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo "jq is not installed. Installing..."
    sudo apt-get update && sudo apt-get install -y jq
fi

# Create the port mappings
echo "# Port mappings" > /etc/nginx/sse-mappings.conf
echo "map \$service_id \$backend_port {" >> /etc/nginx/sse-mappings.conf
echo "    default \"\";" >> /etc/nginx/sse-mappings.conf

# Add port mappings
jq -r 'to_entries[] | "    \(.key) \(.value.port);"' /etc/nginx/sse-mappings.json >> /etc/nginx/sse-mappings.conf

echo "}" >> /etc/nginx/sse-mappings.conf
echo "" >> /etc/nginx/sse-mappings.conf

# Create the token validation mappings
echo "# Token validation mappings" >> /etc/nginx/sse-mappings.conf
echo "map \$auth_token\$service_id \$is_valid {" >> /etc/nginx/sse-mappings.conf
echo "    default 0;" >> /etc/nginx/sse-mappings.conf

# Add token mappings
jq -r 'to_entries[] | .value.allowed_tokens[] as $token | "    \"\($token)\"\(.key) 1;"' /etc/nginx/sse-mappings.json >> /etc/nginx/sse-mappings.conf

echo "}" >> /etc/nginx/sse-mappings.conf

# Test the Nginx configuration
nginx -t

if [ $? -eq 0 ]; then
    echo "Configuration generated successfully!"
else
    echo "Error in generated configuration!"
    exit 1
fi
```

```shellscript
sudo chmod +x /etc/nginx/generate-maps.sh
```

```shellscript
sudo nano /etc/nginx/reload-sse-config.sh
```

```shellscript
#!/bin/bash

# Generate the Nginx maps
sudo /etc/nginx/generate-maps.sh
if [ $? -ne 0 ]; then
    echo "Failed to generate Nginx maps. Aborting reload."
    exit 1
fi

# Test Nginx configuration
sudo nginx -t
if [ $? -ne 0 ]; then
    echo "Nginx configuration test failed. Aborting reload."
    exit 1
fi

# Reload Nginx
sudo systemctl reload nginx
echo "Configuration reloaded successfully!"
```

```shellscript
sudo /etc/nginx/generate-maps.sh
```

```shellscript
sudo /etc/nginx/reload-sse-config.sh
```

