pour lancer les fixtures :
 
php bin/console doctrine:fixtures:load --append

(l'option append permet d'ajouter les données sans effacer les données existantes en base)

pour lancer une (ou plusieurs) fixtures :

php bin/console doctrine:fixtures:load --group=NameOfFixture --group=NameOfOtherFixture