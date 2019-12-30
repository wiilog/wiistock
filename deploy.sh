#!/bin/bash

#echo '-> numéro de version ?'
#read version
#
## mise à jour numéro de version sur template
#firstLineTwig="{% set version = '"$version"' %}"
#sed -i "1s/.*/$firstLineTwig/" templates/layout.html.twig
#echo 'OK : numéro de version mis à jour sur le template'
#
## mise à jour numéro de version sur services.yaml
#forthLineYaml="    nomade_versions: '>="$version"'"
#nomadeVersion=${version//\./-}
#fifthLineYaml="    nomade_apk: 'http://wiilog.fr/dl/wiistock/app-"$nomadeVersion".apk'"
#sed -i "4s/.*/$forthLineYaml/" config/services.yaml
##sed -i "5s/.*/$fifthLineYaml/" config/services.yaml
#echo 'OK : numéros de version mis à jour sur le services.yaml'

## commit et push modifs version
#git add config/services.yaml
#git add templates/layout.html.twig
#git commit -m "version $version"
#git push
#echo 'OK : commit et push modif version'

## mise à jour jira
#read -p "-> mise à jour des tâches sur jira"
#echo "OK : mise à jour des tâches sur jira"

# choix de l'instance
printf '-> déployer sur quelle instance ?'
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
sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@$ip <<EOF
  cd /var/www/"$instance"/WiiStock
  sed -i "6s/.*/APP_ENV=maintenance/" .env
EOF
printf "//////////\nOK : mise en maintenance de l'instance $instance\n//////////"

# sauvegarde base données
case "$instance" in
#TODO CG enlever cl1-rec après tests
  cl2-prod | scs1-prod | col1-rec | cl1-rec)
    db=$(awk '{ print $1 }' ./db-cl1-rec)
    dbuser=$(awk ' {print $2} ' ./db-cl1-rec)
    password=$(awk ' {print $3} ' ./db-cl1-rec);;
  * ) dbuser='noBackup';;
esac

if [ "$dbuser" != 'noBackup' ]; then
  read -p "-> lancer la sauvegarde de la base de données ?"
  date=$(date '+%Y-%m-%d')
#  mysqldump --host=cb249510-001.dbaas.ovh.net --user="$dbuser" --port=35403 --password="$password" "$db" > /root/db_backups/svg_"$db"_"$date".sql
  printf "//////////\nOK : base de données $db sauvegardée\n//////////"

else
  printf "//////////\npas de sauvegarde de base de données nécessaire\n//////////"
fi

# git pull
# migrations et mise à jour bdd
# fixtures
# fin de maintenance
read -p "-> lancer git pull + migrations + fixtures + fin de maintenance ?"
sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@$ip <<EOF
  cd /var/www/"$instance"/WiiStock
#  git pull
  printf "//////////\nOK : git pull effectué\n//////////"
  php bin/console doctrine:migrations:migrate
  php bin/console doctrine:schema:update --force
  printf "//////////\nOK : migrations de la base effectuées\n//////////"
  php bin/console doctrine:fixtures:load --append --group=fixtures
  printf "//////////\nOK : fixtures effectuées\n//////////"
  sed -i "6s/.*/APP_ENV=prod/" .env
  printf "//////////\nOK : mise en prod de l'instance $instance\n//////////"
  php bin/console cache:clear
  chmod 777 -R /var/www/"$instance"/WiiStock/var/cache/
  printf "//////////\nOK : cache nettoyé\n//////////"
EOF