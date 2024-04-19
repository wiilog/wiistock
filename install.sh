#!/bin/sh

set -e

execute_query() {
    mysql "$2" -h "$DATABASE_HOST" -P "$DATABASE_PORT" -u "$DATABASE_USER" -p"$DATABASE_PASSWORD" -sse "$1"
}

prepare_project() {
    mkdir -p /tmp/wiistock-tmp

    # Extract vendor and node_modules from cache if it exists
    wget "https://github.com/wiilog/wiistock/releases/download/$WIISTOCK_VERSION/vendor.zip" -P /tmp/wiistock-tmp || true
    if [ -f /tmp/wiistock-tmp/vendor.zip ]; then
        unzip -q /tmp/wiistock-tmp/vendor.zip -d /project
    fi

    composer install \
        --no-dev \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-ansi

    if [ -z "$APP_CONTEXT" ]; then
        APP_CONTEXT="prod"
    fi

    BUILD_ZIP_NAME="build-$APP_CONTEXT.zip"
    wget "https://github.com/wiilog/wiistock/releases/download/$WIISTOCK_VERSION/$BUILD_ZIP_NAME" -P /tmp/wiistock-tmp || true
    if [ -f "/tmp/wiistock-tmp/$BUILD_ZIP_NAME" ]; then
        unzip -q "/tmp/wiistock-tmp/$BUILD_ZIP_NAME" -d /project/public

        if [ -d public/build ]; then
            find "public/$BUILD_ZIP_NAME" -type f -exec sed -i "s/<<DOMAIN_NAME>>/$APP_DOMAIN_NAME/g" {} \;
        fi
    fi

    # si /public/build existe pas on le crée
    if [ ! -d public/build ]; then
        wget "https://github.com/wiilog/wiistock/releases/download/$WIISTOCK_VERSION/node_modules.zip" -P /tmp/wiistock-tmp || true
        if [ -f /tmp/wiistock-tmp/node_modules.zip ]; then
            unzip -q /tmp/wiistock-tmp/node_modules.zip -d /project
        else
            yarn install
        fi
        build_yarn
    fi

    rm -rf /tmp/wiistock-tmp
    php bin/console app:initialize
}

install_symfony() {
    TABLE_COUNT=$(execute_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DATABASE_NAME';")
    last_command_status=$?
    if [ $last_command_status -ne 0 ]; then
        echo "Failed to access database with error code $last_command_status, aborting installation"
        exit 1
    elif [ "$TABLE_COUNT" = "0" ]; then
        echo "New instance, creating database"

        php bin/console doctrine:schema:update --force
        php bin/console doctrine:migrations:sync-metadata-storage
        php bin/console doctrine:migrations:version --add --all --no-interaction
    else
        echo "Existing instance, updating database"

        php bin/console doctrine:migrations:migrate --no-interaction --dry-run
        last_command_status=$?
        if [ $last_command_status -ne 0 ]; then
            echo "Failed to execute migrations dry-run with error code $last_command_status, aborting deployment"
            exit 1
        fi;

        php bin/console doctrine:migrations:migrate --no-interaction
        last_command_status=$?
        if [ $last_command_status -ne 0 ]; then
            echo "Failed to execute migrations with error code $last_command_status, aborting deployment"
            exit 1
        fi;

        php bin/console doctrine:schema:update --force --dump-sql
        last_command_status=$?
        if [ $last_command_status -ne 0 ]; then
            echo "Failed to execute schema update with error code $last_command_status, aborting deployment"
            exit 1
        fi;
    fi

    php bin/console doctrine:fixtures:load --append --group types
    php bin/console doctrine:fixtures:load --append --group fixtures
    last_command_status=$?
    if [ $last_command_status -ne 0 ]; then
        echo "Failed to execute fixtures with error code $last_command_status, aborting deployment"
        exit 1
    fi;

    php bin/console app:update:translations

    php bin/console cache:clear
    php bin/console cache:warmup
}

build_yarn() {
    php bin/console fos:js-routing:dump --format=json --target=public/generated/routes.json
    php bin/console app:update:fixed-fields

    yarn build:only:production
}

cd /project
echo '{"parameters":{"session_lifetime": 1440}}' > config/generated.yaml

prepare_project
install_symfony

echo "Current date: $(date)"
