import path from "path";
const downloadsFolder = Cypress.config('downloadsFolder');
Cypress.Commands.add('readDownloadFile', (intercepts, downloadName) => {
    if (!Array.isArray(intercepts)) {
        intercepts = [intercepts];
    }

    if (intercepts.length === 1) {
        cy.wait(intercepts, {timeout: 100000}).then((intercept) => {
            const filename = intercept.response.headers['content-disposition'].split('filename=')[1];
            const downloadedFilename = path.join(downloadsFolder, filename);
            cy.readFile(downloadedFilename).should('exist');
        });
    } else {
        cy.wait(intercepts, {timeout: 150000})
            .then((multipleIntercepts) => {
                multipleIntercepts.forEach((intercept, index) => {
                    const filename = intercept.response.headers['content-disposition'].split('filename=')[1];
                    const downloadedFilename = path.join(downloadsFolder, filename);
                    cy.readFile(downloadedFilename).should('exist');
                    expect(filename).to.equal(downloadName[index]);
                })
            })
    }
})

// This piece of code inserted before the action triggering the download allows avoids the page load timeout (Cypress problem when we click on the link to download the file)
Cypress.Commands.add('preventPageLoading', () => {
    cy.window().then(win => {
        const triggerAutIframeLoad = () => {
            const AUT_IFRAME_SELECTOR = '.aut-iframe';

            // get the application iframe
            const autIframe = win.parent.document.querySelector(AUT_IFRAME_SELECTOR);

            if (!autIframe) {
                throw new ReferenceError(`Failed to get the application frame using the selector '${AUT_IFRAME_SELECTOR}'`);
            }

            autIframe.dispatchEvent(new Event('load'));
            // remove the event listener to prevent it from firing the load event before each next unload (basically before each successive test)
            win.removeEventListener('beforeunload', triggerAutIframeLoad);
        };

        win.addEventListener('beforeunload', triggerAutIframeLoad);
    });
});
