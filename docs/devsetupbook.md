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


## On a Devin.ai machine

The following assumes a vanilla Ubuntu 22.04 Devin machine.

    curl https://mise.run/bash | sh
    source ~/.bashrc
    sudo add-apt-repository --yes ppa:ondrej/php
    sudo apt install php8.4-cli php8.4-curl php8.4-fpm php8.4-xml php8.4-mbstring php8.4-mysql php8.4-intl php8.4-gd php8.4-opcache php8.4-bcmath php8.4-zip php8.4-dev php8.4-apcu php8.4-igbinary php8.4-mcrypt php-pear libzstd-dev composer mariadb-client-10.6
    curl -1sLf https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh | sudo -E bash 
    sudo apt install symfony-cli
    docker run \
        --name maas-webapp-db \
        -p 127.0.0.1:3306:3306 \
        -e MYSQL_ROOT_PASSWORD=secret \
        -d \
        mariadb:10.6.16 \
        --character-set-server=utf8mb4 \
        --collation-server=utf8mb4_unicode_ci
    cd repos/maas-webapp
    mise install
    composer install
    npm install --no-save
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate --no-interaction
    mise run quality
    mise run tests
