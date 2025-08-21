# Production Setup Verification Guide

This document outlines how to verify that the Docker management works correctly in the production environment where Symfony runs natively.

## Prerequisites Verification

### 1. Privileged Docker Access (via wrapper + sudoers)
Install and verify the sudo-based Docker wrapper approach:

```bash
# Install sudoers entry (as root)
install -o root -g root -m 0440 /var/www/prod/maas-webapp/docs/infrastructure/etc/sudoers.d/101-www-data-docker-cli-wrapper /etc/sudoers.d/101-www-data-docker-cli-wrapper
visudo -c

# Verify www-data can run controlled Docker commands via the wrapper (no password)
sudo -u www-data sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh ps
```

### 2. Docker Network Setup
Ensure the shared network exists:
```bash
# Create network if it doesn't exist
docker network create mcp_instances

# Verify network exists
docker network ls | grep mcp_instances
```

### 3. Native Nginx Configuration
Verify nginx is configured for internal access:
```bash
# Test nginx is listening on port 8090
curl -I http://localhost:8090

# Verify nginx is NOT listening on 80/443
netstat -tlnp | grep :80
netstat -tlnp | grep :443
```

## Docker Management Verification

### 1. Container Creation Test (from Symfony)
From the native Symfony application, test container creation using the domain service (which will call the wrapper under the hood if configured to use `sudo`):
```php
// Via Symfony console or test script
$dockerService = $container->get(ContainerManagementService::class);
$instance = // ... create test McpInstance
$success = $dockerService->createContainer($instance);
```

### 2. Manual Docker Command Test (via wrapper)
Test the equivalent actions via the sudo wrapper (replace the container name as appropriate):
```bash
# List managed containers
sudo -u www-data sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh ps

# Inspect container state
sudo -u www-data sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh inspect mcp-instance-<slug>

# Restart container
sudo -u www-data sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh restart mcp-instance-<slug>

# For deeper troubleshooting as root (optional)
docker exec mcp-instance-<slug> curl -f http://localhost:8080/mcp
docker exec mcp-instance-<slug> curl -f http://localhost:6080
```

### 3. Traefik Integration Test
Verify Traefik can discover and route to containers:
```bash
# Check Traefik discovers the container
curl http://localhost:8080/api/http/routers | jq '.[] | select(.rule | contains("mcp-test123"))'

# Test actual routing (requires DNS or hosts file)
curl -H "Host: mcp-test123.mcp-as-a-service.com" http://localhost/mcp
```

## Common Issues and Solutions

### 1. Permission Denied on Docker Socket (wrapper not escalating)
```bash
# Ensure you invoke via sudo from the www-data context
sudo -u www-data sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh ps

# Validate sudoers entry is in place and correct
sudo visudo -c
sudo -l -U www-data | grep maas-docker-wrapper.sh

# Confirm wrapper path matches sudoers
ls -l /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh
cat /etc/sudoers.d/101-www-data-docker-cli-wrapper

# Optional fallback (less restrictive): add www-data to docker group
# sudo usermod -aG docker www-data && systemctl restart php*-fpm
```

### 2. Network Communication Issues
```bash
# Verify Traefik can reach host nginx
docker exec traefik-container curl http://host.docker.internal:8080

# Alternative: Use host networking for Traefik
docker run --network host traefik:v3.5 ...
```

### 3. Container Network Isolation
```bash
# Ensure both Traefik and MCP containers are on same network
docker network inspect mcp_instances
```

## Production Deployment Checklist

- [ ] Docker daemon installed and running
- [ ] Docker wrapper path: `/var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh`
- [ ] Sudoers entry installed for wrapper (no password, restricted command)
- [ ] Docker network `mcp_instances` created
- [ ] Native nginx configured on port 8090
- [ ] Traefik container deployed with host access
- [ ] DNS wildcard record configured
- [ ] Test MCP instance creation via Symfony
- [ ] Verify end-to-end HTTP routing
- [ ] Test ForwardAuth endpoint functionality
