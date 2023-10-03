
/* This command will reset the database to the state of the sql file passed as parameter (without the .sql extension)
OPTIONAL PARAMETER: pathToFile: path to the sql file. Default: /var/www/cypress/fixtures/
*/
Cypress.Commands.add('resetDatabase', (sqlFileName = 'BDD_scratch.cypress.sql', pathToFile = '/var/www/cypress/fixtures/'  ) => {
    cy.curlDatabase(sqlFileName);
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT $MYSQL_DATABASE < ${pathToFile}/${sqlFileName}`,
        { failOnNonZeroExit: false});
})

Cypress.Commands.add('curlDatabase', (sqlFileName) => {
    cy.exec(`cd cypress/fixtures && curl -LO https://ftp.wiilog.fr/cypress/${sqlFileName} && cd ../..`);
})

// todo : run d:m:m after resetDatabase
// mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P $MYSQL_PORT $MYSQL_DATABASE < /var/www/cypress/fixtures/BDD_scratch.cypress.sql
