# Dev Setupbook

How do I get a development environment for this application up and running?


## On macOS

- Ensure that `php --version` resolves to PHP 8.4.x
- Clone this repository
- cd into the cloned repo root folder
- Run `composer install`
- Run `nvm install`
- Run `npm install --no-save`
- Run `php bin/console importmap:install`
- Run `bash bin/install-git-hooks.sh`
- Run `php bin/console doctrine:database:create --if-not-exists`
- Run `php bin/console doctrine:migrations:migrate`
- Run `bash bin/build-frontend.sh`
- Run `symfony server:start`

### Running quality checks and tests

Use the dxcli commands via nvm to ensure the correct Node version is active:

- Code quality checks:
  `nvm use && .dxcli/dxcli.sh quality`

- Full test suite:
  `nvm use && .dxcli/dxcli.sh test`

### Optional: Local reverse proxy (Traefik) for end-to-end testing

If you want to simulate the production routing locally (including per-instance subdomains), you can launch Traefik in development mode:

```
bash bin/launch-traefik.sh --dev
```

Notes:
- Development mode enables an insecure local dashboard on `http://localhost:8080` and does not configure TLS certificates.
- You may need hosts entries for subdomains like `mcp-<slug>.localhost` depending on your setup.
