#!/bin/bash

echo '-> numéro de version web ? (entrée si pas de modif)'
read versionWeb
echo '-> numéro de version nomade (si différent)'
read versionNomade

# mise à jour des numéros de version (si demandé)
if [ "$versionWeb" != "" ]; then

  if [ "$versionNomade" = "" ]; then
    versionNomade=$versionWeb
  fi

  # mise à jour numéro de version sur template
  firstLineTwig="{% set version = '"$versionWeb"' %}"
  sed -i "1s/.*/$firstLineTwig/" templates/layout.html.twig
  echo '////////// OK : numéro de version web mis à jour sur le template //////////'

  # mise à jour numéro de version sur services.yaml
  formerFourthLineYaml="nomade_versions:"
  newFourthLineYaml="nomade_versions: '>="$versionNomade"'"
  sed -i "s/$formerFourthLineYaml.*/$newFourthLineYaml/" config/services.yaml

  versionNomadeFormatted=${versionNomade//\./-}
  formerFifthLineYaml="nomade_apk: 'http:\/\/wiilog.fr\/dl\/wiistock"
  newFifthLineYaml="nomade_apk: 'http:\/\/wiilog.fr\/dl\/wiistock\/app-$versionNomadeFormatted.apk'"
  sed -i "s/$formerFifthLineYaml.*/$newFifthLineYaml/" config/services.yaml

  echo '////////// OK : numéros de version nomade mis à jour sur le services.yaml //////////'

  # commit et push modifs version
  git add config/services.yaml
  git add templates/layout.html.twig
  git commit -m "version $version"
  git push
  printf "////////// OK : commit et push modif version $version //////////"
fi

# mise à jour jira
read -p "-> pense à mettre à jour le numéro de version des tâches sur jira !"

# choix de l'instance
echo '-> déployer sur quelle instance ?'
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

printf "////////// OK : mise en maintenance de l'instance $instance //////////\n"

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
    printf "////////// OK : base de données $db sauvegardée //////////\n"
  else
    echo "////////// pas de sauvegarde de base de données //////////"
  fi
else
  echo "////////// pas de sauvegarde de base de données nécessaire //////////"
fi

# préparation fixtures supplémentaires
echo "-> lancer des fixtures supplémentaires ? (nomFixture1 nomFixture2)"
IFS=' '
read fixtures
read -ra FIXT <<< "$fixtures"

fixturesGroups="--group=fixtures"
fixturesMsg="////////// OK : fixtures [fixtures"
if [ "$fixtures" != '' ]
then
  for i in "${FIXT[@]}"; do
    fixturesGroups="${fixturesGroups} --group=$i"
  done
  fixturesMsg= "${fixturesMsg}, $fixtures"
fi
fixturesMsg= "${fixturesMsg}] effectuées //////////"


# préparation environnement à rétablir
case "$instance" in
  test | dev | '') env=dev;;
  * ) env=prod;;
esac

# git pull / migrations et mise à jour bdd / fixtures / fin de maintenance
if [ "$ip" = '51.77.202.108' ]; then
    cd /var/www/"$instance"/WiiStock
    git pull
    echo "////////// OK : git pull effectué //////////"
    php bin/console doctrine:migrations:migrate
    php bin/console doctrine:schema:update --force
    echo "////////// OK : migrations de la base effectuées //////////"
    php bin/console doctrine:fixtures:load --append $fixturesGroups
    echo "$fixturesMsg"
    sed -i "s/APP_ENV.*/APP_ENV=$env/" .env
    echo "////////// OK : mise en environnement de $env de l'instance $instance //////////"
    php bin/console cache:clear
    chmod 777 -R /var/www/"$instance"/WiiStock/var/cache/
    echo "////////// OK : cache nettoyé //////////"
else
  sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@"$ip" <<EOF
    cd /var/www/"$instance"/WiiStock
    git pull
    echo "////////// OK : git pull effectué //////////"
    php bin/console doctrine:migrations:migrate
    php bin/console doctrine:schema:update --force
    echo "////////// OK : migrations de la base effectuées //////////"
    php bin/console doctrine:fixtures:load --append $fixturesGroups
    echo "$fixturesMsg"
    sed -i "s/APP_ENV.*/APP_ENV=$env/" .env
    echo "////////// OK : mise en environnement de $env de l'instance $instance //////////"
    php bin/console cache:clear
    chmod 777 -R /var/www/"$instance"/WiiStock/var/cache/
    echo "////////// OK : cache nettoyé //////////"
EOF
fi

echo "////////// OK : déploiement sur $instance terminé ! //////////"