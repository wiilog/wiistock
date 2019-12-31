#!/bin/bash

#echo '-> numéro de version web ?'
#read versionWeb
#echo '-> numéro de version nomade (si différent)'
#read versionNomade
#
#if [ "$versionNomade" = "" ]; then
#  versionNomade=$versionWeb
#fi
#
## mise à jour numéro de version sur template
#firstLineTwig="{% set version = '"$versionWeb"' %}"
#sed -i "1s/.*/$firstLineTwig/" templates/layout.html.twig
#echo '////////// OK : numéro de version web mis à jour sur le template //////////'
#
## mise à jour numéro de version sur services.yaml
#formerFourthLineYaml="nomade_versions:"
#newFourthLineYaml="nomade_versions: '>="$versionNomade"'"
#sed -i "s/$formerFourthLineYaml.*/$newFourthLineYaml/" config/services.yaml
#
#versionNomadeFormatted=${versionNomade//\./-}
#formerFifthLineYaml="nomade_apk: 'http:\/\/wiilog.fr\/dl\/wiistock"
#newFifthLineYaml="nomade_apk: 'http:\/\/wiilog.fr\/dl\/wiistock\/app-$versionNomadeFormatted.apk'"
#sed -i "s/$formerFifthLineYaml.*/$newFifthLineYaml/" config/services.yaml
#
#echo '////////// OK : numéros de version nomade mis à jour sur le services.yaml //////////'
#
## commit et push modifs version
#git add config/services.yaml
#git add templates/layout.html.twig
#git commit -m "version $version"
#git push
#echo '////////// OK : commit et push modif version //////////'
#
## mise à jour jira
#read -p "-> pense à mettre à jour le numéro de version des tâches sur jira !"
#
## choix de l'instance
#echo '-> déployer sur quelle instance ?'
#while true; do
#  read instance
#  case "$instance" in
#    dev | test )
#      ip=51.77.202.108
#      break;;
#    cl2-prod | cl1-rec | scs1-prod | scs1-rec )
#      ip=145.239.76.51
#      break;;
#    col1-prod | col1-rec )
#      ip=51.38.34.237
#      break;;
#    * ) echo 'instances disponibles : cl2-prod, cl1-rec, scs1-prod, scs1-rec, col1-prod, col1-rec, test, dev';;
#  esac
#done
#
## mise en maintenance
#sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@$ip <<EOF
#  cd /var/www/"$instance"/WiiStock
#  sed -i "6s/.*/APP_ENV=maintenance/" .env
#EOF
#printf "////////// OK : mise en maintenance de l'instance $instance //////////\n"
#
## sauvegarde base données
#case "$instance" in
#  cl2-prod | scs1-prod | col1-rec)
#    db=$(awk '{ print $1 }' ./db-"$instance")
#    dbuser=$(awk ' {print $2} ' ./db-"$instance")
#    password=$(awk ' {print $3} ' ./db-"$instance");;
#  * ) dbuser='noBackup';;
#esac
#
#if [ "$dbuser" != 'noBackup' ]; then
#  read -p "-> lancer la sauvegarde de la base de données ?"
#  date=$(date '+%Y-%m-%d')
#  mysqldump --host=cb249510-001.dbaas.ovh.net --user="$dbuser" --port=35403 --password="$password" "$db" > /root/db_backups/svg_"$db"_"$date".sql
#  printf "////////// OK : base de données $db sauvegardée //////////\n"
#
#else
#  echo "////////// pas de sauvegarde de base de données nécessaire //////////"
#fi

# git pull
# migrations et mise à jour bdd
# fixtures
# fin de maintenance
read -p "-> lancer git pull + migrations + fixtures + fin de maintenance ?"
echo "-> lancer des fixtures supplémentaires ? (nomFixture1,nomFixture2 / n)"
read fixtures

# préparation fixtures supplémentaires
if [ "$fixtures" != 'n' ]; then
  fixturesCmd='php bin/console doctrine:fixtures:load'
  for i in "${fixtures[@]}"; do
    fixturesCmd="${fixturesCmd} --group=$i"
  done
    fixturesMsg="////////// OK : fixtures [$fixtures] effectuées //////////"
else
  fixturesCmd=''
  fixturesMsg=''
fi

# préparation environnement à rétablir
case "$instance" in
  test | dev ) env=dev;;
  * ) env=prod;;
esac

if [ "$ip" = 'ip=51.77.202.108' ]; then
    cd /var/www/"$instance"/WiiStock
    git pull
    echo "////////// OK : git pull effectué //////////"
    php bin/console doctrine:migrations:migrate
    php bin/console doctrine:schema:update --force
    echo "////////// OK : migrations de la base effectuées //////////"
    php bin/console doctrine:fixtures:load --append --group=fixtures
    echo "////////// OK : fixtures effectuées //////////"
    echo $fixturesCmd
    echo $fixturesMsg
    sed -i "6s/.*/APP_ENV=$env/" .env
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
    php bin/console doctrine:fixtures:load --append --group=fixtures
    echo "////////// OK : fixtures effectuées //////////"
    echo $fixturesCmd
    echo $fixturesMsg
    sed -i "6s/.*/APP_ENV=$env/" .env
    echo "////////// OK : mise en environnement de $env de l'instance $instance //////////"
    php bin/console cache:clear
    chmod 777 -R /var/www/"$instance"/WiiStock/var/cache/
    echo "////////// OK : cache nettoyé //////////"
EOF
fi

echo "////////// OK : déploiement sur $instance terminé ! //////////"