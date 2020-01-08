`exécute toutes les migrations (si pas déjà executées)`
php bin/console doctrine:migrations:migrate

`exécute une seule migration`
php bin/console doctrine:migrations:execute --up numero_migration

`ajoute une migrations à la base pour ne plus l'exécuter`
php bin/console doctrine:migrations:version numero_migration --add