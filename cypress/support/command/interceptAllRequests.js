let previousNumberOfCalls = 0;
let numeroOfSettingsItemStopped = 0;
let numero = 0;
let alreadyBrowsed = false;
Cypress.Commands.add('interceptAllRequets', () => {
    cy.intercept({resourceType: /script|document/}, {log: false}).as('allRequests');
    let numberOfCalls = 0;
    cy.get('@allRequests.all').then((calls) => {
        numberOfCalls = calls.length - previousNumberOfCalls;
        previousNumberOfCalls = calls.length;
        for (let i = 0; i < numberOfCalls; i++) {
            cy.wait('@allRequests', {timeout: 40000}).then((interceptions) => {
                const interceptedResponses = [interceptions];
                interceptedResponses.forEach((interception) => {
                    //expect(interception.response.statusCode).not.to.eq(500);
                });
            });

        }
    })
})

Cypress.Commands.add('returnToTheCorrectPage', (settingLink, numeroOfSettingsItemStopped) => {
    cy.intercept({resourceType: /script|document/}, {log: false}).as('allRequests');
    cy.visit(settingLink)
        .then(() => {
            alreadyBrowsed = false;
            let firstSettingsItemName;
            // To get the data-menu of the first settings item to check if we have been redirected to another page
            cy.get('.settings-item').eq(0).invoke('attr', 'data-menu').then((name) => {
                firstSettingsItemName = name;
            })
            // To browse each settings item
            cy.get('.settings-item').then((settingsItem) => {
                for (let i = numeroOfSettingsItemStopped; i < settingsItem.length; i++) {
                    // To detect if we have been redirected to another page
                    cy.get('.settings-item').eq(0).invoke('attr', 'data-menu').then((name) => {
                        // If we have been redirected to another page, we browse each settings item of the new page loaded
                        if (!alreadyBrowsed) {
                            if (firstSettingsItemName !== name) {
                                alreadyBrowsed = true;
                                // We memorise the settings item that loaded a new page
                                numeroOfSettingsItemStopped = i;
                                cy.get('.settings-item').then((settingsItemClick) => {
                                    for (let j = 0; j < settingsItemClick.length; j++) {
                                        cy.get('.settings-item').eq(j).click({force: true});
                                        cy.interceptAllRequets();
                                    }
                                })
                                //cy.returnToTheCorrectPage(settingLink, numeroOfSettingsItemStopped);
                            } else {
                                cy.get('.settings-item').eq(i).click({force: true});
                                cy.interceptAllRequets();
                            }
                        }
                    })
                }
            })
        })
})



