`exécute toutes les migrations (si pas déjà executées)`
php bin/console doctrine:migrations:migrate

`exécute une seule migration`
php bin/console doctrine:migrations:execute --up numero_migration