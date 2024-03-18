#!/bin/sh

throw_errors() {
    set -e
}

ignore_errors() {
    set +e
}

throw_errors

execute_query() {
    mysql "$2" -h "$DATABASE_HOST" -P "$DATABASE_PORT" -u "$DATABASE_USER" -p"$DATABASE_PASSWORD" -sse "$1"
}

prepare_project() {
    # Extract vendor and node_modules from cache if it exists

    wget https://github.com/wiilog/wiistock/releases/download/"$WIISTOCK_VERSION"/vendor.zip || true
    if [ -f vendor.zip ]; then
        unzip -q vendor.zip
    fi

    composer install \
        --no-dev \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-ansi

    wget https://github.com/wiilog/wiistock/releases/download/"$WIISTOCK_VERSION"/node_modules.zip || true
    if [ -f node_modules.zip ]; then
        unzip -q node_modules.zip
    else
        yarn install
    fi

    php bin/console app:initialize || true
}

install_symfony() {
    ignore_errors

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

    throw_errors
}

install_yarn() {
    php bin/console fos:js-routing:dump --format=json --target=public/generated/routes.json
    php bin/console app:update:fixed-fields

    yarn build:only:production
}

cd /project
echo '{"parameters":{"session_lifetime": 1440}}' > config/generated.yaml

prepare_project
install_symfony
install_yarn

echo "Current date: $(date)"
