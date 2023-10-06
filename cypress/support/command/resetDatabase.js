
let OLD_DATABASE_NAME = 'wiistock';

/* This command will reset the database to the state of the sql file passed as parameter (without the .sql extension)
OPTIONAL PARAMETER: pathToFile: path to the sql file. Default: /var/www/cypress/fixtures/
*/
Cypress.Commands.add(
    'startingCypressEnvironnement',
    (oldDatabaseName = 'wiistock',
     newDatabaseName = 'cypress_dev',
     sqlFileName = 'BDD_cypress.sql',
     pathToFile = '/etc/sqlscripts') => {

    // change .env
    cy.changeDatabase(newDatabaseName);
    // yarn & yarn build & composer install
    cy.buildAndInstallDependencies();
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
})

Cypress.Commands.add('changeDatabase', (newDatabaseName) => {
    cy.exec(`grep DATABASE_URL .env.local`, { failOnNonZeroExit: false }).then((result) => {
        if (result.stdout.includes('DATABASE_URL')) {
            const currentLine = result.stdout.trim();
            const currentDatabaseName = currentLine.split('/').pop();
            const newLine = currentLine.replace(currentDatabaseName, newDatabaseName);

            cy.exec(`sed -i 's|${currentLine}|${newLine}|' .env.local`);
        }
    });
})

Cypress.Commands.add('buildAndInstallDependencies', () => {
    cy.exec(`ssh_on_app 'cd /var/www && composer install'`);
    cy.exec(`ssh_on_app 'cd /var/www && yarn'`);
    cy.exec(`ssh_on_app 'cd /var/www && yarn build'`);
})

Cypress.Commands.add('dropAndRecreateDatabase', () => {
    // run sql files to drop and recreate database
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT < /var/www/cypress/fixtures/drop_and_recreate_database.sql`);
})

Cypress.Commands.add('curlDatabase', (urlToFTP, pathToFile = '/etc/sqlscripts', fileName = 'BDD_cypress.sql') => {
    cy.exec(`curl -o ${pathToFile}/${fileName} ${urlToFTP}`);
})

Cypress.Commands.add('runDatabaseScript', (sqlFileName, pathToFile ) => {
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT $MYSQL_DATABASE_CYPRESS < ${pathToFile}/${sqlFileName}`,
        { failOnNonZeroExit: false});
})

Cypress.Commands.add('doctrineMakeMigration', () => {
    cy.exec(`ssh_on_app '/usr/local/bin/php /var/www/bin/console d:m:m --no-interaction'`);
})

Cypress.Commands.add('doctrineSchemaUpdate', () => {
    cy.exec(`ssh_on_app '/usr/local/bin/php /var/www/bin/console d:s:u --force --no-interaction'`);
})

Cypress.Commands.add('doctrineFixturesLoad', () => {
    cy.exec(`ssh_on_app '/usr/local/bin/php /var/www/bin/console d:f:l --append --group=fixtures --no-interaction'`);
})

