`pour lancer toutes les fixtures étiquetées 'fixtures':`
php bin/console doctrine:fixtures:load --append --group=fixtures

`pour lancer les fixtures (ATTENTION efface les données en base) :`
php bin/console doctrine:fixtures:load

`sans effacer les données existantes en base`
php bin/console doctrine:fixtures:load --append

`pour lancer une ou pls fixtures seulement`
php bin/console doctrine:fixtures:load --group=NameOfFixture --group=NameOfOtherFixture

`pour lancer les fixtures d'un group donné`
`ajouter implements FixtureGroupInterface + ajouter méthode :
    public static function getGroups():array {
        return ['types'];
    }`
php bin/console doctrine:fixtures:load --group=groupName

`ex. pour lancer l'import des articles :`
php bin/console doctrine:fixtures:load --append --group=articles