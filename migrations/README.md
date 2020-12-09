# Migrations

## Commandes
Exécute toutes les migrations
```sh
php bin/console doctrine:migrations:migrate
```

Exécute la migration sélectionnée
```sh
php bin/console doctrine:migrations:execute --up <migration>
```

Ajoute une migration à la base sans l'exécuter. Il est possible
d'ajouter l'argument `--all` pour le faire pour toutes les migrations.
```sh
php bin/console doctrine:migrations:version <migration> --add
```



## Nettoyage
Pour nettoyer le dossier de migration, il faut renommer le fichier 
`Version20201209000000.php` en la date du jour et supprimer toutes 
les autres migrations. Il ne doit rester aucune migration. Si des
migrations ont été créées mais pas encore exécutées en production,
il faut d'abord les exécuter puis faire le nettoyage. 
