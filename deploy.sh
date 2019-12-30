#!/bin/bash
#echo 'numéro de version ?'
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
#
## mise à jour jira
#read -p "mise à jour des tâches sur jira"
#echo "OK : mise à jour des tâches sur jira"

# choix de l'instance
echo 'sur quelle instance déployer ? (cl2prod, cl1rec, scs1prod, scs1rec, col1prod, col1rec, test, dev)'
read instance

case "$instance" in
  dev | test ) ip=51.77.202.108;;
  cl2prod | cl1rec | scs1prod | scs1rec ) ip=145.239.76.51;;
  col1prod | col1rec ) ip=51.38.34.237;;
  * ) echo 'instances disponibles : cl2prod, cl1rec, scs1prod, scs1rec, col1prod, col1rec, test, dev';;
esac

# mise en maintenance
echo "$ip"
password=AkMHvXP7
sshpass -p "$password" ssh -o StrictHostKeyChecking=no root@$ip
ls
#echo "$instance"
#sixthLineEnv="APP_ENV=maintenance"
#sed -i "6s/.*/$sixthLineEnv/" .env
#echo exit