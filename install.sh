#!/bin/sh

function throw_errors() {
    set -e
}

function ignore_errors() {
    set +e
}

throw_errors

OPTIONS="$@"

has_option() {
    MATCH="$1"

    if test "${OPTIONS#*$MATCH}" != "$OPTIONS" ; then
        return 0
    else
        return 1
    fi
}

execute_query() {
    mysql $2 -h $DATABASE_HOST -P $DATABASE_PORT -u $DATABASE_USER -p$DATABASE_PASSWORD -sse "$1"
}

prepare_project() {
    # Extract vendor and node_modules from cache if it exists
    if [ -f /cache/cache.tar.gz ]; then
        tar xzf /cache/cache.tar.gz
    fi

    chmod -R 777 /project/public/generated -r

    composer install \
        --no-dev \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-ansi

    yarn install

    if has_option "--with-initialization"; then
        php bin/console app:initialize || true
    fi

    if has_option "--with-fonts"; then
        php bin/console app:initialize || true
    fi
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

        if has_option "--with-migrations"; then
            php bin/console doctrine:schema:update --force
            php bin/console doctrine:migrations:sync-metadata-storage
            php bin/console doctrine:migrations:version --add --all --no-interaction
        else
            php bin/console doctrine:schema:update --force
        fi
    else
        echo "Existing instance, updating database"

        if has_option "--with-migrations"; then
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
        else
            echo -n 0 > /tmp/migrations
        fi

        php bin/console doctrine:schema:update --dump-sql
        php bin/console doctrine:schema:update --force
        last_command_status=$?
        if [ $last_command_status -ne 0 ]; then
            echo "Failed to execute schema update with error code $last_command_status, aborting deployment"
            exit 1
        fi;
    fi

    if has_option "--with-fixtures"; then
        php bin/console doctrine:fixtures:load --append --group types
        php bin/console doctrine:fixtures:load --append --group fixtures
        last_command_status=$?
        if [ $last_command_status -ne 0 ]; then
            echo "Failed to execute fixtures with error code $last_command_status, aborting deployment"
            exit 1
        fi;
    fi

    if has_option "--with-translations"; then
        php bin/console app:update:translations
    fi

    php bin/console cache:clear
    php bin/console cache:warmup

    throw_errors
}

install_yarn() {
    php bin/console fos:js-routing:dump --format=json --target=public/generated/routes.json
    yarn build:only:production || true
}

cd /project
echo '{"parameters":{"session_lifetime": 1440}}' > config/generated.yaml

prepare_project
install_symfony

install_yarn

echo "Current date: $(date)"
