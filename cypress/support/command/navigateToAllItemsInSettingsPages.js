let alreadyBrowsed = false;
let numeroOfSettingsItemStopped = 0;
let stopLoop = false;

Cypress.Commands.add('navigateToAllItemsInSettingsPages', (settingLink, newLink = true) => {
    cy.intercept({resourceType: /script|document/}, {log: false}).as('allRequests');
    if (newLink) {
        numeroOfSettingsItemStopped = 0;
        stopLoop = false;
    }
    cy.visit(settingLink)
        .then( () => {
            alreadyBrowsed = false;
            let firstSettingsItemName;
            cy.get('body').then(($body) => {
                if ($body.find('.settings-item').length) {
                    // To get the data-menu of the first settings item to check if we have been redirected to another page
                    cy.get('.settings-item').eq(0).invoke('attr', 'data-menu').then((name) => {
                        firstSettingsItemName = name;

                        // To browse each settings item
                        cy.get('.settings-item').then( (settingsItem) => {
                            cy.wrap(settingsItem).each((item, index, list) => {
                                if (index >= numeroOfSettingsItemStopped) {
                                    // To detect if we have been redirected to another page
                                    cy.get('.settings-item').eq(0).invoke('attr', 'data-menu').then((name) => {

                                        // If we have been redirected to another page, we browse each settings item of the new page loaded
                                        if (!alreadyBrowsed) {
                                            if (firstSettingsItemName !== name) {
                                                alreadyBrowsed = true;
                                                // We memorise the settings item that loaded a new page
                                                cy.get('.settings-item').then((settingsItemClick) => {
                                                    for (let j = 0; j < settingsItemClick.length; j++) {
                                                        cy.get('.settings-item').eq(j).click({force: true});
                                                        cy.interceptAllRequets();
                                                    }
                                                    numeroOfSettingsItemStopped = index;
                                                })
                                                if (index <= (settingsItem.length - 1) && !stopLoop) {
                                                    if(index === (settingsItem.length - 1) ){
                                                        stopLoop = true;
                                                    }
                                                    cy.navigateToAllItemsInSettingsPages(settingLink, false);
                                                }
                                            } else {
                                                cy.get('.settings-item').eq(index).click({force: true});
                                                numeroOfSettingsItemStopped = index;
                                                cy.interceptAllRequets();
                                            }
                                        }
                                    })
                                }
                            })
                        })
                    })
                } else {
                    cy.interceptAllRequets();
                }
            })
        })
})
