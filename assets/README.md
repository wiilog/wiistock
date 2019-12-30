

## Installation du Sass Symfony

<https://symfony.com/doc/current/frontend/encore/simple-example.html>

### Installer yarn pour window :
<https://yarnpkg.com/lang/en/docs/install/#windows-stable>

### Dans le powershell modifier le php.ini
* Récupérer le chemin du php.ini
```bash
php --ini
```
* modifier le champ memory_limit à 1

### Installer les librairies
```bash
composer install
yarn
```

### Compile assets

* Compiler assets, --watch optionnel
```bash
yarn encore dev --watch
```

* Create a production build
```bash
yarn encore production
```

