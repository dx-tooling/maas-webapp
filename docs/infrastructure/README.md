# Host-level infrastructure config (moved)

The host `/etc` files this app needs — the nginx vhosts that serve the apex/app on
`:8090`, and the `www-data` sudoers wrappers — are **owned by the `infrastructure`
repo**, not here. They live under `/etc`, so they need root to install; that is the
root-run infra provisioner's job, not this app's (`www-data`) deploy.

**Ownership rule:** `/etc` + root → the `infrastructure` repo; app code + containers → this repo.

- Canonical files: `infrastructure/rootserver-hosting/host/`
  - `nginx/sites-enabled/app.mcp-as-a-service.com.conf`, `nginx/sites-enabled/mcp-as-a-service.com.conf`
  - `sudoers/100-www-data-mcp-proxy-gen`, `sudoers/101-www-data-docker-cli-wrapper`
- Installed (validated with `nginx -t` / `visudo -cf`) by: `infrastructure/ansible/host-config.yml`
- The stock `/etc/nginx/nginx.conf` is the Ubuntu package default (not customized), so it isn't tracked.

This app still owns the scripts those sudoers grant: `bin/docker-cli-wrapper.sh` and
`bin/generate-mcp-proxies.sh`.
