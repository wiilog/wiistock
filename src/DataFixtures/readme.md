`pour lancer les fixtures :`
php bin/console doctrine:fixtures:load

`sans effacer les données existantes en base`
php bin/console doctrine:fixtures:load --append

`pour lancer une ou pls fixtures seulement`
php bin/console doctrine:fixtures:load --group=NameOfFixture --group=NameOfOtherFixture