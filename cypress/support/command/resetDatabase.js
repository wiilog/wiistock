
/* This command will reset the database to the state of the sql file passed as parameter (without the .sql extension)
OPTIONAL PARAMETER: pathToFile: path to the sql file. Default: /var/www/cypress/fixtures/
*/
Cypress.Commands.add('resetDatabase', (sqlFileName = 'BDD_scratch.cypress.sql', pathToFile = '/var/www/cypress/fixtures/'  ) => {
    cy.curlDatabase(sqlFileName);
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT $MYSQL_DATABASE < ${pathToFile}/${sqlFileName}`,
        { failOnNonZeroExit: false});
    cy.doctrineMakeMigration();
})

Cypress.Commands.add('curlDatabase', (sqlFileName) => {
    cy.exec(`cd cypress/fixtures && curl -LO https://ftp.wiilog.fr/cypress/${sqlFileName} && cd ../..`);
})

Cypress.Commands.add('doctrineMakeMigration', () => {
    cy.exec('sshpass -p root ssh root@174.20.128.2')
    cy.exec(`cd /var/www && php bin/console d:m:m --no-interaction`);
})

// mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT $MYSQL_DATABASE < /var/www/cypress/fixtures/BDD_scratch.cypress.sql
