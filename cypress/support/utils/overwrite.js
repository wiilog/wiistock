// This file is used to overwrite the default Cypress commands.

/*
  * overwrite the default visit command to avoid fail on 4xx and 5xx status code
 */
Cypress.Commands.overwrite("visit", (originalFn, url, options) => {
    const newOptions = {
        ...options,
        failOnStatusCode: false
        // Add any other options you want to include here
    };

    return originalFn(url, newOptions);
});
