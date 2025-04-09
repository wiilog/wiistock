// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

require('cypress-failed-log');
import 'cypress-failed-log';
Cypress.on('uncaught:exception', (err, runnable) => {
    if (err.message.includes('printing error')) {
        return false
    }
    if (err.message.includes('Cannot read properties of undefined')) {
        return false
    }
});

// Import commands.js using ES2015 syntax:
import './command/select2'
import './command/session'
import './command/general'
import './command/settings'
import './command/downloadFile'
import './command/interceptAllRequests'
import './command/movements'
import './command/resetDatabase'
import './command/datatable'
import './command/modal'
