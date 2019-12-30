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
echo '-> déployer sur quelle instance ?'
read instance

case "$instance" in
  dev | test ) ip=51.77.202.108;;
  cl2-prod | cl1-rec | scs1-prod | scs1-rec ) ip=145.239.76.51;;
  col1-prod | col1-rec ) ip=51.38.34.237;;
  * ) echo 'instances disponibles : cl2-prod, cl1-rec, scs1-prod, scs1-rec, col1-prod, col1-rec, test, dev';exit;;
esac

# mise en maintenance
sshpass -f pass-"$ip" ssh -o StrictHostKeyChecking=no root@$ip <<EOF
  cd /var/www/"$instance"/WiiStock
  sed -i "6s/.*/APP_ENV=maintenance/" .env
EOF
echo "OK : mise en maintenance de l'instance $instance"

# sauvegarde base données
# cacheclear
# git pull
# cacheclear
# migrations
# mise à jour base données
# fixtures
# fin de maintenance
# cacheclear
