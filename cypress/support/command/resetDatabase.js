Cypress.env('OLD_DATABASE_NAME', 'wiistock');
let SSH_ON_APP = 'sshpass -p $SSHPASS ssh -o StrictHostKeyChecking=no www-data@wiistock-php'

Cypress.Commands.add(
    'startingCypressEnvironnement',
    (needCurl = false,
     sqlFileName = 'BDD_cypress.sql',
     pathToFile = '/etc/sqlscripts') => {

        //get the shema of db in .env.local file
        cy.exec(`grep DATABASE_URL .env.local`, {failOnNonZeroExit: false}).then((result) => {
            if (result.stdout.includes('DATABASE_URL')) {
                const currentLine = result.stdout.trim();
                const currentDatabaseName = currentLine.split('/').pop();
                Cypress.env('OLD_DATABASE_NAME', currentDatabaseName);

                // get the CYPRESS_baseUrl environment variable
                const baseUrl = Cypress.config('baseUrl');
                // change APP_URL in .env.local
                cy.exec(`sed -i 's|APP_URL=http://localhost|APP_URL=${baseUrl}|' .env.local`);
                // print the current database name
                cy.log(`Current database name: ${currentDatabaseName}`);

                //delete and recreate database
                cy.dropAndRecreateDatabase(currentDatabaseName);

                //curl sql file
                if (needCurl) {
                    cy.curlDatabase(pathToFile, sqlFileName);
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
                //clear cache before build
                cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /project/bin/console app:cache:clear'`);
                //fixtures fixed fields
                cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /project/bin/console app:update:fixed-fields'`);
                 // build assets
                //cy.exec(`${SSH_ON_APP} 'cd /project && yarn build'`, {timeout: 120000});
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

Cypress.Commands.add('dropAndRecreateDatabase', (databaseName = "wiistock") => {
    // run sql files to drop and recreate database
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P 3306 --execute="DROP DATABASE IF EXISTS ${databaseName}; CREATE DATABASE ${databaseName};"`);
})

Cypress.Commands.add('curlDatabase', (pathToFile = '/etc/sqlscripts', fileName = 'BDD_cypress.sql') => {
    cy.exec(`curl -u $FTP_USER:$FTP_PASSWORD $FTP_HOST/cypress/SQL_script/dev-script.sql -o ${pathToFile}/${fileName}`);
})

Cypress.Commands.add('runDatabaseScript', (sqlFileName, pathToFile, databaseName ) => {
    cy.exec(`mysql -h $MYSQL_HOSTNAME -u root -p$MYSQL_ROOT_PASSWORD -P 3306 ${databaseName} < ${pathToFile}/${sqlFileName}`);
})

Cypress.Commands.add('doctrineMakeMigration', () => {
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /project/bin/console d:m:m --no-interaction'`);
})

Cypress.Commands.add('doctrineSchemaUpdate', () => {
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /project/bin/console d:s:u --force --no-interaction --dump-sql --complete'`);
})

Cypress.Commands.add('doctrineFixturesLoad', () => {
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /project/bin/console d:f:l --append --group=types --no-interaction'`);
    cy.exec(`${SSH_ON_APP} '/usr/local/bin/php /project/bin/console d:f:l --append --group=fixtures --no-interaction'`);
})

