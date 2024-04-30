#!/bin/sh

set -e

execute_query() {
    mysql "$2" -h "$DATABASE_HOST" -P "$DATABASE_PORT" -u "$DATABASE_USER" -p"$DATABASE_PASSWORD" -sse "$1"
}

prepare_project() {
    echo ">>>>>> clear if we are in crash loop"
    rm -rf /tmp/wiistock-deploy || true
    rm -rf /project/public/build || true
    rm -rf /project/node_modules || true
    rm -rf /project/vendor || true


    echo ">>>>>> create tmp directory for zip download"
    mkdir -p /tmp/wiistock-deploy

    echo ">>>>>> wget vendor.zip"
    wget "https://github.com/wiilog/wiistock/releases/download/$WIISTOCK_VERSION/vendor.zip" -P /tmp/wiistock-deploy || true
    if [ -f /tmp/wiistock-deploy/vendor.zip ]; then
        echo ">>>>>> unzip vendor.zip"
        unzip -q /tmp/wiistock-deploy/vendor.zip -d /project
    fi

    echo ">>>>>> composer install"
    composer install \
        --no-dev \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-ansi

    if [ -z "$APP_CONTEXT" ]; then
        APP_CONTEXT="prod"
    fi

    echo ">>>>>> wget build.zip"
    BUILD_ZIP_NAME="build-$APP_CONTEXT.zip"
    wget "https://github.com/wiilog/wiistock/releases/download/$WIISTOCK_VERSION/$BUILD_ZIP_NAME" -P /tmp/wiistock-deploy || true
    if [ -f "/tmp/wiistock-deploy/$BUILD_ZIP_NAME" ]; then
        echo ">>>>>> unzip build.zip"
        unzip -q "/tmp/wiistock-deploy/$BUILD_ZIP_NAME" -d /project/public

        if [ -d public/build ]; then
            echo ">>>>>> replace <<DOMAIN_NAME>>"
            find public/build -type f -exec sed -i "s/<<DOMAIN_NAME>>/$APP_DOMAIN_NAME/g" {} \;
        fi
    fi

    # Extract public/build from cache if it exists
    # else extract node_modules from cache and run yarn build
    if [ ! -d public/build ]; then
        echo ">>>>>> wget node_modules.zip"
        wget "https://github.com/wiilog/wiistock/releases/download/$WIISTOCK_VERSION/node_modules.zip" -P /tmp/wiistock-deploy || true
        if [ -f /tmp/wiistock-deploy/node_modules.zip ]; then
            echo ">>>>>> unzip node_modules.zip"
            unzip -q /tmp/wiistock-deploy/node_modules.zip -d /project
        else
            echo ">>>>>> yarn install"
            yarn install
        fi
        build_yarn
    fi

    rm -rf /tmp/wiistock-deploy || true
}

install_symfony() {
    echo ">>>>>> install_symfony"
    TABLE_COUNT=$(execute_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DATABASE_NAME';")
    last_command_status=$?
    if [ $last_command_status -ne 0 ]; then
        echo "Failed to access database with error code $last_command_status, aborting installation"
        exit 1
    elif [ "$TABLE_COUNT" = "0" ]; then
        echo ">>>>>> New instance, creating database"

        echo ">>>>>> php bin/console doctrine:schema:update --force"
        php bin/console doctrine:schema:update --force

        echo ">>>>>> php bin/console doctrine:migrations:sync-metadata-storage"
        php bin/console doctrine:migrations:sync-metadata-storage

        echo ">>>>>> php bin/console doctrine:migrations:version --add --all --no-interaction"
        php bin/console doctrine:migrations:version --add --all --no-interaction
    else
        echo ">>>>>> Existing instance, updating database"

        echo ">>>>>> php bin/console doctrine:migrations:migrate --no-interaction --dry-run"
        php bin/console doctrine:migrations:migrate --no-interaction --dry-run

        echo ">>>>>> php bin/console doctrine:migrations:migrate --no-interaction"
        php bin/console doctrine:migrations:migrate --no-interaction

        echo ">>>>>> php bin/console doctrine:schema:update --force --dump-sql"
        php bin/console doctrine:schema:update --force --dump-sql
    fi


    echo ">>>>>> php bin/console doctrine:fixtures:load --append --group types"
    php bin/console doctrine:fixtures:load --append --group types

    echo ">>>>>> php bin/console doctrine:fixtures:load --append --group fixtures"
    php bin/console doctrine:fixtures:load --append --group fixtures

    echo ">>>>>> php bin/console app:update:translations"
    php bin/console app:update:translations

    echo ">>>>>> php bin/console app:initialize"
    php bin/console app:initialize

    echo ">>>>>> php bin/console cache:clear"
    php bin/console cache:clear

    echo ">>>>>> php bin/console cache:warmup"
    php bin/console cache:warmup
}

build_yarn() {
    echo ">>>>>> php bin/console fos:js-routing:dump --format=json --target=assets/generated/routes.json"
    php bin/console fos:js-routing:dump --format=json --target=assets/generated/routes.json

    echo ">>>>>> php bin/console app:update:fixed-fields"
    php bin/console app:update:fixed-fields

    echo ">>>>>> yarn build:only:production"
    yarn build:only:production
}

cd /project

echo ">>>>>> Set default session_lifetime in config/generated.yaml"
echo '{"parameters":{"session_lifetime": 1440}}' > config/generated.yaml

prepare_project
install_symfony

echo "Current date: $(date)"
