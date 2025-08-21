#  Devbook

How do I solve recurring tasks and problems during development?


## Building the frontend

- `bash bin/build-frontend.sh`

## Local Traefik (optional)

To simulate production routing locally:

```
bash bin/launch-traefik.sh --dev
```

This starts Traefik with an insecure dashboard on `http://localhost:8080` and without TLS.


## Updating all dependencies

- `composer update --with-dependencies`
- `nvm use && npm update`
- `php bin/console importmap:update`


## Changing the database schema with migrations

- Create new or edit existing entities
- Run `php bin/console make:migration`


## Connect to the local database

- `bash bin/connect-to-db.sh`
