const {defineConfig} = require("cypress");
const path = require('path');

module.exports = defineConfig({
    video: true,
    e2e: {
        // To show all files in Cypress UI
        specPattern: "cypress/e2e/**/*.*",
        viewportWidth: 1280,
        viewportHeight: 720,
        baseUrl: 'http://localhost/',
        downloadsFolder: 'cypress/downloads',
        setupNodeEvents(on, config) {
            require('cypress-failed-log/on')(on)
        },
    },
    /*resolve: {
        alias: {
            '@support': path.resolve(__dirname, '/cypress/support'),
            '@command': path.resolve(__dirname, '/command'),
        },
    },*/
    user: {
        email: 'admin@wiilog.fr',
        password: 'Admin1234!',
    }
});
