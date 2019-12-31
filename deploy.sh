#!/bin/bash

echo '-> numéro de version web ? (entrée si pas de modif)'
read versionWeb
echo '-> numéro de version nomade ? (entrée si pas de modif, = si idem version web)'
read versionNomade

# mise à jour des numéros de version (si demandé)
needCommit=false

if [ "$versionWeb" != "" ]; then
  # mise à jour numéro de version sur template
  firstLineTwig="{% set version = '"$versionWeb"' %}"
  sed -i "1s/.*/$firstLineTwig/" templates/layout.html.twig
  echo '////////// OK : numéro de version web mis à jour sur le template //////////'
  needCommit=true
fi

if [ "$versionNomade" != "" ]; then
  if [ "$versionNomade" = "=" ]; then
    versionNomade=$versionWeb
  fi

  # mise à jour numéro de version sur services.yaml
  formerNomadeVersionLine="nomade_versions:"
  newNomadeVersionLine="nomade_versions: '>="$versionNomade"'"
  sed -i "s/$formerNomadeVersionLine.*/$newNomadeVersionLine/" config/services.yaml

  # mise à jour lien apk sur services.yaml
  versionNomadeFormatted=${versionNomade//\./-}
  formerApkLine="nomade_apk: 'http:\/\/wiilog.fr\/dl\/wiistock"
  newApkLine="nomade_apk: 'http:\/\/wiilog.fr\/dl\/wiistock\/app-$versionNomadeFormatted.apk'"
  sed -i "s/$formerApkLine.*/$newApkLine/" config/services.yaml

  echo '////////// OK : numéro de version nomade + lien apk mis à jour sur le services.yaml //////////'
  needCommit=true
fi

if [ "$needCommit" = true ]; then
  # commit et push modifs version
  git add config/services.yaml
  git add templates/layout.html.twig
  git commit -m "version $version"
  git push
  printf "\n////////// OK : commit et push modif version $version //////////\n"
fi

# actions manuelles : mise à jour jira
read -p "-> pense à mettre à jour le numéro de version des tâches sur jira !"
echo ''
# mise à jour branches à déployer
read -p "-> maintenant mets à jour la branche distante à déployer"
echo ''

# choix de l'instance
echo -n '-> déployer sur quelle instance ? '
while true; do
  read instance
  case "$instance" in
    dev | test )
      ip=51.77.202.108
      break;;
    cl2-prod | cl1-rec | scs1-prod | scs1-rec )
      ip=145.239.76.51
      break;;
    col1-prod | col1-rec )
      ip=51.38.34.237
      break;;
    * ) echo 'instances disponibles : cl2-prod, cl1-rec, scs1-prod, scs1-rec, col1-prod, col1-rec, test, dev';;
  esac
done

# mise en maintenance
if [ "$ip" = '51.77.202.108' ]; then
    cd /var/www/"$instance"/WiiStock
    sed -i "s/APP_ENV.*/APP_ENV=maintenance/" .env
else
  sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@$ip <<EOF
    cd /var/www/"$instance"/WiiStock
    sed -i "s/APP_ENV.*/APP_ENV=maintenance/" .env
EOF
fi

printf "\n////////// OK : mise en maintenance de l'instance $instance //////////\n"

# sauvegarde base données
case "$instance" in
  cl2-prod | scs1-prod | col1-rec)
    db=$(awk '{ print $1 }' ./db-"$instance")
    dbuser=$(awk ' {print $2} ' ./db-"$instance")
    password=$(awk ' {print $3} ' ./db-"$instance");;
  * ) dbuser='noBackup';;
esac

if [ "$dbuser" != 'noBackup' ]; then
  echo -n "-> lancer la sauvegarde de la base de données (entrée/n) ? "
  read -r backup
  if [ "$backup" != 'n' ]; then
    date=$(date '+%Y-%m-%d')
    mysqldump --host=cb249510-001.dbaas.ovh.net --user="$dbuser" --port=35403 --password="$password" "$db" > /root/db_backups/svg_"$db"_"$date".sql
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
read -ra FIXT <<< "$fixtures"

fixturesGroups="--group=fixtures"
fixturesMsg="////////// OK : fixtures"
if [ "$fixtures" != '' ]
then
  for i in "${FIXT[@]}"; do
    fixturesGroups="${fixturesGroups} --group=$i"
  done
  fixturesMsg="${fixturesMsg} [$fixtures]"
fi
fixturesMsg="${fixturesMsg} effectuées //////////"

# préparation environnement à rétablir
case "$instance" in
  test | dev | '') env=dev;;
  * ) env=prod;;
esac

# git pull / migrations et mise à jour bdd / fixtures / fin de maintenance
if [ "$ip" = '51.77.202.108' ]; then
    cd /var/www/"$instance"/WiiStock
    git pull
    printf "\n////////// OK : git pull effectué //////////\n"
    php bin/console doctrine:migrations:migrate
    php bin/console doctrine:schema:update --force
    printf "\n////////// OK : migrations de la base effectuées //////////\n"
    php bin/console doctrine:fixtures:load --append $fixturesGroups
    printf "\n$fixturesMsg\n"
    sed -i "s/APP_ENV.*/APP_ENV=$env/" .env
    printf "\n////////// OK : mise en environnement de $env de l'instance $instance //////////\n"
    php bin/console cache:clear
    chmod 777 -R /var/www/"$instance"/WiiStock/var/cache/
    printf "\n////////// OK : cache nettoyé //////////\n"
else
  sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@"$ip" <<EOF
    cd /var/www/"$instance"/WiiStock
    git pull
    printf "\n////////// OK : git pull effectué //////////\n"
    php bin/console doctrine:migrations:migrate
    php bin/console doctrine:schema:update --force
    printf "\n////////// OK : migrations de la base effectuées //////////\n"
    php bin/console doctrine:fixtures:load --append $fixturesGroups
    printf "\n$fixturesMsg\n"
    sed -i "s/APP_ENV.*/APP_ENV=$env/" .env
    printf "\n////////// OK : mise en environnement de $env de l'instance $instance //////////\n"
    php bin/console cache:clear
    chmod 777 -R /var/www/"$instance"/WiiStock/var/cache/
    printf "\n////////// OK : cache nettoyé //////////\n"
EOF
fi

printf "\n////////// OK : déploiement sur $instance terminé ! //////////\n"