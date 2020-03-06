#!/bin/bash
source $(dirname "$0")/utils.sh

read -p '-> mettre à jour le numéro de version web et le numéro de version mobile'
echo ''

# actions manuelles : mise à jour jira
read -p "-> mettre à jour le numéro de version des tâches sur jira !"
echo ''

# mise à jour branches à déployer
read -p "-> mettre à jour la branche distante à déployer"
echo ''

# choix de l'instance
echo -n '-> déployer sur quelle instance ? '
instance=$(script::readInstance)
serverName=$(script::getServerName "$instance")

# mise en maintenance
if [ "$serverName" = 'server-dev' ]; then
    cd /var/www/"$instance"/WiiStock && replaceInFile "APP_ENV.*" "APP_ENV=maintenance" ".env"
else
  remote::changeEnv "$instance" maintenance "$serverName"
fi

printf "\n////////// OK : mise en maintenance de l'instance $instance //////////\n"

# sauvegarde base données
backup=true
case "$instance" in
cl2-prod)
  db=cl1prod;;
scs1-prod)
  db=scs1prod;;
col1-prod)
  db=col1prod;;
col2-prod)
  db=col2prod;;
col1-rec)
  db=col1rec;;
sed1-rec)
  db=sed1rec;;
sed1-prod)
  db=sed1prod;;
*) backup=false ;;
esac

if [ "$backup" = true ]; then
    echo -n "-> lancer la sauvegarde de la base de données (entrée/n) ? "
    read -r backup
    if [ "$backup" != 'n' ]; then
        exportDB "$db"
        printf "\n////////// OK : base de données $db sauvegardée //////////\n"
    else
        printf "\n////////// pas de sauvegarde de base de données //////////\n"
    fi
else
    printf "\n////////// pas de sauvegarde de base de données nécessaire //////////\n"
fi

# préparation fixtures supplémentaires
printf "\n-> lancer des fixtures supplémentaires ? (nomFixture1 nomFixture2)\n"
IFS=' '
read fixtures
read -ra FIXT <<<"$fixtures"

fixturesGroups="--group=fixtures"
fixturesMsg="////////// OK : fixtures"
if [ "$fixtures" != '' ]; then
    for i in "${FIXT[@]}"; do
        fixturesGroups="${fixturesGroups} --group=$i"
    done
    fixturesMsg="${fixturesMsg} [$fixtures]"
fi
fixturesMsg="${fixturesMsg} effectuées //////////"

# préparation environnement à rétablir
case "$instance" in
test | dev | '') env=dev ;;
*) env=prod ;;
esac

# git pull / migrations et mise à jour bdd / fixtures
commandsToRun=("git pull § \n////////// OK : git pull effectué //////////\n § \n////////// KO : git pull //////////\n")

printf "\n-> lancer composer install ? (entrée/n)\n"
read doComposerInstall
if [ "$doComposerInstall" != 'n' ]; then
    commandsToRun+=("composer install § \n////////// OK : composer install //////////\n § \n////////// KO : composer install //////////\n")
fi

printf "\n-> lancer yarn install ? (entrée/n)\n"
read doYarnInstall
if [ "$doYarnInstall" != 'n' ]; then
    commandsToRun+=("yarn install § \n////////// OK : yarn install //////////\n § \n////////// KO : yarn install //////////\n")
fi

commandsToRun+=(
    "php bin/console doctrine:migrations:migrate && php bin/console doctrine:schema:update --force § \n////////// OK : migrations de la base effectuées //////////\n § ////////// KO : migrations //////////\n"
    "php bin/console doctrine:fixtures:load --append $fixturesGroups § \n$fixturesMsg\n § ////////// KO : fixtures //////////\n"
    "yarn build § \n////////// OK : yarn encore //////////\n § \n////////// KO : yarn encore //////////\n"
    "php bin/console app:update:translations § \n////////// OK : config traductions à jour //////////\n § \n////////// KO : config traductions //////////\n"
    "replaceInFile \"APP_ENV\" \"APP_ENV=$env\" \".env\" § \n////////// OK : mise en environnement de $env de l'instance $instance //////////\n § \n////////// KO : mise en environnement de $env de l'instance $instance //////////\n"\
    "php bin/console cache:clear && chmod 777 -R /var/www/$instance/WiiStock/var/cache/ § \n////////// OK : nettoyage du cache //////////\n § \n////////// KO : nettoyage du cache //////////\n"
)

script::deploy "$serverName" "$instance" "${commandsToRun[@]}"
echo -e "\n////////// OK : déploiement sur $instance terminé ! //////////\n"
