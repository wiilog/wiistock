const {defineConfig} = require("cypress");


module.exports = defineConfig({
    video: true,
    reporter: 'cypress-mochawesome-reporter',
    e2e: {
        // To show all files in Cypress UI
        specPattern: "cypress/e2e/**/*.*",
        viewportWidth: 1280,
        viewportHeight: 720,
        baseUrl: 'http://localhost/',
        downloadsFolder: 'cypress/downloads',
        reporterOptions: {
            charts: true,
            reportPageTitle: 'Cypress Inline Reporter',
            embeddedScreenshots: true,
            inlineAssets: true, //Adds the asserts inline
        },
        setupNodeEvents(on, config) {
            require('cypress-failed-log/on')(on);
            require('cypress-mochawesome-reporter/plugin')(on);
        },
    },
    user: {
        email: 'admin@wiilog.fr',
        password: 'Admin1234!',
    }
});
