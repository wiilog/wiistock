Cypress.env('OLD_DATABASE_NAME', 'wiistock');
let SSH_ON_APP = 'sshpass -p $SSHPASS ssh -o StrictHostKeyChecking=no www-data@app'

Cypress.Commands.add(
    'startingCypressEnvironnement',
    (urlToCurl = 'https://github.com/wiilog/wiistock/releases/download/v7.8.8/cypressDB.sql',
     sqlFileName = 'dev-script.sql',
     pathToFile = '/cypress/SQL_script') => {

        //get the shema of db in .env.local file
        cy.exec(`grep DATABASE_URL .env.local`, {failOnNonZeroExit: false}).then((result) => {
            if (result.stdout.includes('DATABASE_URL')) {
                const currentLine = result.stdout.trim();
                const currentDatabaseName = currentLine.split('/').pop();
                Cypress.env('OLD_DATABASE_NAME', currentDatabaseName);

                // print the current database name
                cy.log(`Current database name: ${currentDatabaseName}`);

                //delete and recreate database
                cy.dropAndRecreateDatabase(currentDatabaseName);

                //curl sql file
                if (urlToCurl !== undefined) {
                    cy.curlDatabase(urlToCurl, pathToFile, sqlFileName);
                } else {
                    // if the is no url to curl, we use the sql file in the project
                    pathToFile = 'cypress/fixtures';
                    sqlFileName = 'dev-script.sql';
                }
                //run sql file
                cy.runDatabaseScript(sqlFileName, pathToFile, currentDatabaseName);
                //make migration
                cy.doctrineMakeMigration();
                //update schema
                cy.doctrineSchemaUpdate();
                //load fixtures
                cy.doctrineFixturesLoad();
                // yarn & yarn build & composer install
                cy.buildAndInstallDependencies();
            }
        });
    })

Cypress.Commands.add('changeDatabase', (newDatabaseName) => {
    cy.exec(`grep DATABASE_URL .env.local`, {failOnNonZeroExit: false}).then((result) => {
        if (result.stdout.includes('DATABASE_URL')) {
            const currentLine = result.stdout.trim();
            const currentDatabaseName = currentLine.split('/').pop();
            const newLine = currentLine.replace(currentDatabaseName, newDatabaseName);
            Cypress.env('OLD_DATABASE_NAME', currentDatabaseName);
            cy.exec(`sed -i 's|${currentLine}|${newLine}|' .env.local`);
        }
    });
})

Cypress.Commands.add('buildAndInstallDependencies', () => {
    cy.exec(`${SSH_ON_APP} 'cd /var/www && composer install'`, {timeout: 120000});
    cy.exec(`${SSH_ON_APP} 'cd /var/www && yarn'`, {timeout: 120000});
    cy.exec(`${SSH_ON_APP} 'cd /var/www && yarn build'`, {timeout: 120000});
})

Cypress.Commands.add('dropAndRecreateDatabase', (databaseName = "wiistock") => {
    // run sql files to drop and recreate database
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P 3306 --execute="DROP DATABASE IF EXISTS ${databaseName}; CREATE DATABASE ${databaseName};"`);
})

Cypress.Commands.add('curlDatabase', (urlToFTP, pathToFile = '/etc/sqlscripts', fileName = 'BDD_cypress.sql') => {
    cy.exec(`curl -o --user $FTP_USER:$FTP_PASSWORD  ${pathToFile}/${fileName} ${urlToFTP}`);
})

Cypress.Commands.add('runDatabaseScript', (sqlFileName, pathToFile, databaseName ) => {
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P 3306 ${databaseName} < ${pathToFile}/${sqlFileName}`);
})

Cypress.Commands.add('doctrineMakeMigration', () => {
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /var/www/bin/console d:m:m --no-interaction'`);
})

Cypress.Commands.add('doctrineSchemaUpdate', () => {
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /var/www/bin/console d:s:u --force --no-interaction'`);
})

Cypress.Commands.add('doctrineFixturesLoad', () => {
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /var/www/bin/console d:f:l --append --group=fixtures --no-interaction'`);
})

