Cypress.env('OLD_DATABASE_NAME', 'wiistock');
let SSH_ON_APP = 'sshpass -p $SSH_ROOT_PASSWORD ssh -o StrictHostKeyChecking=no root@$APP_IP'

Cypress.Commands.add(
    'startingCypressEnvironnement',
    (urlToCurl,
     newDatabaseName = 'cypress_dev',
     sqlFileName = 'BDD_cypress.sql',
     pathToFile = '/etc/sqlscripts') => {

        // change .env
        cy.changeDatabase(newDatabaseName);
        //delete and recreate database
        cy.dropAndRecreateDatabase();
        //curl sql file
        cy.curlDatabase('https://ftp.wiilog.fr/cypress/BDD_scratch_mig.cypress.sql', pathToFile, sqlFileName);
        //run sql file
        cy.runDatabaseScript(sqlFileName, pathToFile)
        //make migration
        cy.doctrineMakeMigration();
        //update schema
        cy.doctrineSchemaUpdate();
        //load fixtures
        cy.doctrineFixturesLoad();
        // yarn & yarn build & composer install
        cy.buildAndInstallDependencies();
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

Cypress.Commands.add('dropAndRecreateDatabase', () => {
    // run sql files to drop and recreate database
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT < /var/www/cypress/fixtures/drop_and_recreate_database.sql`);
})

Cypress.Commands.add('curlDatabase', (urlToFTP, pathToFile = '/etc/sqlscripts', fileName = 'BDD_cypress.sql') => {
    cy.exec(`curl -o ${pathToFile}/${fileName} ${urlToFTP}`);
})

Cypress.Commands.add('runDatabaseScript', (sqlFileName, pathToFile) => {
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT $MYSQL_DATABASE_CYPRESS < ${pathToFile}/${sqlFileName}`,
        {failOnNonZeroExit: false});
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

