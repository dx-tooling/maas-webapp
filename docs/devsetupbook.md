# Dev Setupbook

How do I get a development environment for this application up and running?


## On macOS

- Ensure that `php --version` resolves to PHP 8.4.x
- Ensure that the [`symfony` CLI tool](https://symfony.com/download) is installed
- Ensure that [`mise`](https://mise.jdx.dev/) is installed and set up for your command line sessions
- Clone this repository
- cd into the cloned repo root folder
- Run `mise trust`
- Run `mise install`
- Run `composer install`
- Run `npm install --no-save`
- Run `php bin/console importmap:install`
- Run `php bin/console doctrine:database:create --if-not-exists`
- Run `php bin/console doctrine:migrations:migrate`
- Run `mise run frontend`

The application is now ready to run:

- Run `symfony server:start`
- Open `http://127.0.0.1:8000` in a browser

### Running quality checks and tests

- Code quality checks:
  `mise run quality`

- Full test suite:
  `mise run tests`
