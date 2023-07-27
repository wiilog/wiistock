const {defineConfig} = require("cypress");


module.exports = defineConfig({
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
    user: {
        email: 'admin@wiilog.fr',
        password: 'Admin1234!',
    }
});
