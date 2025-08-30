# Runbook

Operational guide for the Docker + Traefik architecture in production.

## Quick links
- Architecture and routing: `docs/orchestration.md`
- Docker wrapper sudoers entry: `docs/infrastructure/etc/sudoers.d/101-www-data-docker-cli-wrapper`
- Traefik launcher: `bin/launch-traefik.sh`

## Common operational tasks

### Check instance containers (as www-data via wrapper)
```
sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh ps
```

### Inspect a specific container
```
sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh inspect mcp-instance-<slug> | jq '.[0].State'
```

### Restart a container
```
sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh restart mcp-instance-<slug>
```

### Validate endpoints from inside the container
```
sudo -n /var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh exec mcp-instance-<slug> sh -lc "curl -f http://localhost:8080/mcp && curl -f http://localhost:6080"
```

## Traefik operations

### Launch or relaunch Traefik (production)
```
bash /var/www/prod/maas-webapp/bin/launch-traefik.sh --prod
```

Prereqs:
- Docker network `mcp_instances` exists
- Wildcard certs at `/etc/letsencrypt/live/mcp-as-a-service.com/`
- Host nginx listens on 8090

### Check Traefik health
```
docker ps | grep traefik
curl -s http://localhost:8080/api/overview | jq
```

### Show traefik labels for mcp instance container
```
docker inspect mcp-instance-<slug> | jq -r '.[0].Config.Labels
| to_entries[]
| select(.key|startswith("traefik.http."))
| "\(.key)=\(.value)"'
```

## Troubleshooting

### Permission denied on Docker commands
- Ensure sudoers entry matches path: `/var/www/prod/maas-webapp/bin/docker-cli-wrapper.sh`
- Validate: `visudo -c`

### Routing fails for subdomains
- Verify container labels (recreate container if labels are wrong)
- Check Traefik routers: `curl -s http://localhost:8080/api/http/routers | jq`
- Confirm DNS `*.mcp-as-a-service.com` points to the host IP

### App not reachable via app.mcp-as-a-service.com
- Ensure host nginx listens on 8090
- Confirm Traefik service `host-nginx` targets `http://host.docker.internal:8090`
