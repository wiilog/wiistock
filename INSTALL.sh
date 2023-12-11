#!/bin/bash


set -e

is_first_install=0
release_directory=$(pwd)
env_variables=(
    "DATABASE_URL"
    "APP_SECRET"
    "APP_DASHBOARD_TOKEN"
    "APP_URL"
)

if [ -z $1 ]
then
    echo "Vous devez fournir le chemin vers le répertoire FollowNexter";
    exit 1;
fi

follow_nexter_home=$1
origin_branch=$(git rev-parse --abbrev-ref HEAD)


if [ ! -d "$follow_nexter_home" ]
then
    echo "Veuillez extaire le contenu de la release dans le répertoire $follow_nexter_home et réexécuter l'installation";
    exit 1;
fi

if [ ! -f "$follow_nexter_home/.env.local" ]
then
    is_first_install=1
    echo ">>>> Première installation de Follow Nexter";

    echo ">>>> Création du répertoire";
    cp "$follow_nexter_home/.env" "$follow_nexter_home/.env.local"

    ## now loop through the above array
    for env_variable_name in "${env_variables[@]}"
    do
        echo "Entrez la valeur de $env_variable_name:"
        read env_variable_value
        sed -i "s|$env_variable_name=.*|$env_variable_name=$env_variable_value|g" "$follow_nexter_home/.env.local"
    done
fi


cd "$follow_nexter_home" || exit

echo ">>>> Création des fichiers générés"
if [ ! -f "$follow_nexter_home/config/generated.yaml" ]; then
    echo '{"parameters": {"session_lifetime": 1440}}' > "$follow_nexter_home/config/generated.yaml"
fi

echo ">>>> Mise en environnement de maintenance"
sed -i "s/APP_ENV=.*/APP_ENV=maintenance/g" .env.local
php bin/console cache:clear

if [ $is_first_install -ne 1  ]
then
    echo ">>>> Mise à jour";
    echo ">>>> Mise à des dépendances";

    rm -rf "$follow_nexter_home/vendor"
    cp -r "$release_directory/vendor" "$follow_nexter_home/vendor"

    rm -rf "$follow_nexter_home/node_modules"
    cp -r "$release_directory/node_modules" "$follow_nexter_home/node_modules"
fi

echo ">>>> Initialisation du dépôt git"
git remote remove origin
git remote add origin "$release_directory"
git checkout master

if [ $is_first_install -eq 1 ]
then
    echo ">>>> Création de la base de données";
    php bin/console doctrine:database:create >/dev/null

    echo ">>>> Création du schéma de la base de données"
    php bin/console doctrine:schema:update --force
    php bin/console d:m:sync-metadata-storage
    php bin/console doctrine:migrations:version --add --all --no-interaction

    echo ">>>> Initialisation des données par défaut"
    php bin/console doctrine:fixtures:load --append --group=fixtures
    php bin/console doctrine:fixtures:load --append --group=init-user
else
    echo ">>>> Mise à jour des fichiers"
    git pull origin "$origin_branch"

    echo ">>>> Mise à jour de la base de données"
    php bin/console doctrine:migrations:migrate --no-interaction
    php bin/console doctrine:schema:update --force

    echo ">>>> Mise à jour des données par défaut"
    php bin/console doctrine:fixtures:load --append --group=fixtures --group=initialize
fi


echo ">>>> Initialisation des personnalisations de libellés"
php bin/console app:update:translations

echo ">>>> Mise à jour du style & du routing"
php bin/console assets:install --symlink public
yarn build

echo ">>>> Mise en environnement de production"
sed -i "s/APP_ENV=.*/APP_ENV=prod/g" .env.local
rm -rf var/cache
rm -rf cache
rm -rf var/sessions
mkdir -p var/sessions
mkdir -p cache

php bin/console cache:clear
chown -R "$USER:www-data" var/cache var/sessions cache
chmod g+s var/cache var/sessions cache
chmod g+w -R var/cache var/sessions cache

declare -A TIMES
TIMES[dashboard-feeds]="*/5 * * * *"
TIMES[unique-imports]="*/30 * * * *"
TIMES[scheduled-imports]="* * * * *"
TIMES[scheduled-export]="* * * * *"
TIMES[close-inactive-sessions]="*/5 * * * *"
TIMES[average-requests]="0 20 * * *"

declare -A COMMANDS
COMMANDS[dashboard-feeds]="/usr/bin/php $follow_nexter_home/bin/console app:feed:dashboards"
COMMANDS[unique-imports]="/usr/bin/php $follow_nexter_home/bin/console app:launch:unique-imports"
COMMANDS[scheduled-imports]="/usr/bin/php $follow_nexter_home/bin/console app:launch:scheduled-imports"
COMMANDS[scheduled-export]="/usr/bin/php $follow_nexter_home/bin/console app:launch:scheduled-exports"
COMMANDS[close-inactive-sessions]="/usr/bin/php $follow_nexter_home/bin/console app:sessions:close:inactives"
COMMANDS[average-requests]="/usr/bin/php $follow_nexter_home/bin/console app:feed:average:requests"

commands_to_install=(dashboard-feeds unique-imports scheduled-imports scheduled-export close-inactive-sessions average-requests)

tmp_cron_file='/tmp/follow_nexter_cron.tmp'
echo "" > $tmp_cron_file

for command_name in "${commands_to_install[@]}"; do
    echo "${TIMES[$command_name]} ${COMMANDS[$command_name]}" >> "$tmp_cron_file"
done

crontab $tmp_cron_file
rm $tmp_cron_file


cd "$release_directory"
