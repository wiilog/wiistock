const tableRoleName = 'tableRoles';
const user = Cypress.config('user');
describe('Open every pages in nav and settings', () => {

    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
    })

    it('should get all permissions', () => {
        cy.intercept('POST', '/parametrage/users/api-modifier').as('user_api_edit');
        cy.intercept('POST', '/parametrage/utilisateurs/roles/api').as('settings_role_api');
        cy.get('[data-cy-user-header-button]').click().wait('@user_api_edit');
        cy.get('#modalEditUser [name=role] option').first().then(($option) => {
            const roleName = $option.text();
            const roleId = $option.val();
            cy.log(roleName, roleId);
            cy.get('#modalEditUser button.close').click();
            cy.navigateInNavMenu('parametre');
            cy.get('[data-cy-settings-menu=roles]').click().wait('@settings_role_api');
            cy.get(`table#${tableRoleName} tbody tr`).contains(roleName).first().click();
            cy.url().should('include', roleId)
            cy.get('.settings-content input[type=checkbox]').then(($inputs) => {
                $inputs.prop("checked", true);
                cy.get('.save-settings').click({force: true});
            })
        })
    })

    it('should navigate to all nav pages', () => {
        cy.get('nav')
            .click()
            .get('.dropdown-menu')
            .should('be.visible')
            .get('.dropdown-menu a.dropdown-item')
            .each(($navLink) => {
                const href = $navLink.prop('href');
                if ((!href.includes('/logout')) && (!href.includes('https://wiilog.gitbook.io/docs/'))) {
                    cy.visit($navLink.prop('href'))
                        .then(() => {
                            cy.wait(4000);
                            cy.interceptAllRequets();
                        })
                } else {
                    cy.request({
                        url: href,
                    })
                        .should((response) => {
                            expect(response.status).to.eq(200);
                        })
                }
            });
    });

    let numeroOfSettingsItemStopped = 0;
    let alreadyBrowsed = false;

    it.only('should navigate to all settings pages', () => {
        cy.navigateInNavMenu('parametre')
            .get('[data-cy-settings-menu]')
            .each(($settingLink) => {
                const href = $settingLink.prop('href');
                if (!href.includes('/global')) {
                    cy.visit($settingLink.prop('href'))
                        .then(() => {
                            alreadyBrowsed = false;
                            numeroOfSettingsItemStopped = 0;
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
                                                //cy.returnToTheCorrectPage($settingLink.prop('href'), numeroOfSettingsItemStopped);
                                            } else {
                                                cy.get('.settings-item').eq(i).click({force: true});
                                                cy.interceptAllRequets();
                                            }
                                        }
                                    })
                                }
                            })
                        })
                }

            })
    })


    // it('should navigate to all settings pages', () => {
    //     cy.navigateInNavMenu('parametre')
    //         .get('[data-cy-settings-menu]')
    //         .each(($settingLink) => {
    //             cy.request({
    //                 url: $settingLink.prop('href'),
    //                 failOnStatusCode: false
    //             })
    //                 .should((response) => {
    //                     expect(response.status).to.eq(200);
    //                 })
    //         })
    // })
})
