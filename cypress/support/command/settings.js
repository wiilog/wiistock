const linesTableFreeFieldsComponent = 'table[data-table-processing=freeFields] tbody tr';

Cypress.Commands.add('openSettingsItem', (settingsMenuName) => {
    cy.visit('/')
        .navigateInNavMenu('parametre')
        .get(`[data-cy-settings-menu=${settingsMenuName}]`).eq(0)
        .click();
})
Cypress.Commands.add('addTypeInSettings', (settingsItemName) => {
    const labelName = Date.now().toString(36);
    cy.intercept('GET', 'parametrage/champs-libres/api/*').as('settings_free_field_api');
    cy.intercept('POST', 'parametrage/champs-libres/header*').as('settings_type_header');

    cy.get(`[data-menu=${settingsItemName}]`).eq(0)
        .click().wait('@settings_free_field_api');
    cy.get(`[data-menu=${settingsItemName}] button.add-entity`)
        .click().wait('@settings_type_header', {timeout: 8000});
    cy.get('input[name=label]')
        .type(labelName);
    cy.get('input[name=description]')
        .type('Cypress');

    cy.get(`[data-menu=${settingsItemName}] .main-entity-content`)
        .then(($div) => {

            if ($div.find('input[name="color"]').length) {
                cy.get('input[name="color"]')
                    .then(($input) => {
                        cy.get('datalist')
                            .find('option')
                            .then(($options) => {
                                $input.val($options.eq(0).val());
                                cy.wrap($input).trigger('input');
                            });
                    });
            }

            if ($div.find('select[name=pickLocation]').length) {
                cy.select2Ajax('pickLocation', 'BUREAU GT');
                cy.select2AjaxMultiple('suggestedPickLocations', ['BUREAU GT']);
            }

            if ($div.find('select[name=dropLocation]').length) {
                cy.select2Ajax('dropLocation', 'ZONE 41');
                cy.select2AjaxMultiple('suggestedDropLocations', ['ZONE 41']);
            }

            if ($div.find('input[name=pushNotifications]').length) {
                cy.get('input[name=pushNotifications]').check('2');
            }

            if ($div.find('select[name=notificationEmergencies]').length) {
                cy.select2('notificationEmergencies', '24h');
            }

            if ($div.find('#upload-logo').length) {
                cy.get('#upload-logo')
                    .selectFile('cypress/fixtures/logo.jpg', {force: true});
            }
        });
    cy.get('button.save-settings')
        .click().wait('@settings_free_field_api', {timeout: 10000});
    cy.get('[data-name=entity] label')
        .contains(labelName)
        .should('exist');
})

Cypress.Commands.add('editTypeInSettings', (settingsItemName) => {
    const labelName = Date.now().toString(36);
    cy.intercept('GET', 'parametrage/champs-libres/api/*').as('settings_free_field_api');

    cy.get(`[data-menu=${settingsItemName}]`).eq(0)
        .click().wait('@settings_free_field_api');
    cy.get(`[data-menu=${settingsItemName}] button.edit-button`)
        .click().wait('@settings_free_field_api');

    cy.get('.main-entity-content input[name=label]')
        .clear()
        .type(labelName);
    cy.get('.main-entity-content input[name=description]')
        .clear()
        .type('CypressModify');

    cy.get(`[data-menu=${settingsItemName}] .main-entity-content`)
        .then(($div) => {

            if ($div.find('input[name="color"]').length) {
                cy
                    .get('input[name="color"]')
                    .then(($input) => {
                        cy.get('datalist')
                            .find('option')
                            .then(($options) => {
                                $input.val($options.eq(1).val());
                                cy.wrap($input).trigger('input');
                            });
                    });
            }

            if ($div.find('select[name=pickLocation]').length) {
                cy.select2Ajax('pickLocation', 'ZONE 41');
                cy.select2AjaxMultiple('suggestedPickLocations', ['ZONE 41']);
            }

            if ($div.find('select[name=dropLocation]').length) {
                cy.select2Ajax('dropLocation', 'BUREAU GT');
                cy.select2AjaxMultiple('suggestedDropLocations', ['BUREAU GT']);
            }

            if ($div.find('input[name=pushNotifications]').length) {
                cy.get('input[name=pushNotifications]').check('2').then(() => {
                    cy.get('.main-entity-content-item').first().find('select[name=notificationEmergencies]').should(($select) => {
                        if ($select.is(':visible')) {
                            cy.select2('notificationEmergencies', ['24h']);
                        }
                    });
                })
            }




            if ($div.find('input[type=file]').length) {
                const filePath = 'cypress/fixtures/logo.jpg';
                cy.get('#upload-logo')
                    .selectFile(filePath, {force: true});
            }
        });
    cy.get('button.save-settings')
        .click().wait('@settings_free_field_api', {timeout: 80000});
    cy.get('[data-name=entity] label')
        .contains(labelName)
        .should('exist');
})

Cypress.Commands.add('addFreeFieldInSettings', (settingsItemName) => {
    const labelName = Date.now().toString(36);
    cy.intercept('GET', 'parametrage/champs-libres/api/*').as('settings_free_field_api');

    cy.get('div.settings-item').its('length').then((count) => {
        // For Iot section because it has only one item
        if (count !== 1) {
            cy.get(`[data-menu=${settingsItemName}]`).eq(0).click().wait('@settings_free_field_api');
        } else {
            cy.get(linesTableFreeFieldsComponent).find('td').should('have.length.gt', 1);
        }
    }).then(() => {
        cy.get(`[data-menu=${settingsItemName}]`)
            .then(($item) => {

                if ($item.find('button.edit-button').length) {
                    cy.get(`[data-menu=${settingsItemName}] button.edit-button`)
                        .click().wait('@settings_free_field_api');
                    cy.get(linesTableFreeFieldsComponent).last()
                        .click();
                } else {
                    cy.get(`${linesTableFreeFieldsComponent} td .wii-icon-plus`)
                        .click().wait('@settings_free_field_api');
                }
            });
    })

    cy.get('select[name=type]').then(($select) => {
        const typeLength= $select.find('option').length;
        for (let i = 1; i < typeLength; i++) {
            if (i>1) {
                cy.get(`${linesTableFreeFieldsComponent} td .wii-icon-plus`).click()
            }

            cy.get(linesTableFreeFieldsComponent)
                .invoke('removeAttr', 'style')
                .then(() => {

                    cy.get(linesTableFreeFieldsComponent)
                        .then(($elements) => {
                            const penultimate = $elements.eq(-2);
                            cy.wrap(penultimate).find('input[name=label]')
                                .click()
                                .type(labelName + i);

                            cy.wrap(penultimate)
                                .then(($element) => {

                                    if ($element.find('select[name=category]').length) {
                                        cy.wrap($element).find('select[name=category]')
                                            .select(1);
                                    }
                                });

                            cy.wrap(penultimate).find('select[name=type]')
                                .select(i);

                            const typeArray = ['list', 'list multiple']

                            if (typeArray.includes($select.find('option').eq(i).val(), 0)) {
                                cy.wrap(penultimate)
                                    .find('input[name=elements]').click().type('test')
                            }
                            cy.wrap(penultimate).find('input[type=checkbox]')
                                .check();
                        });
                });

            if (i === typeLength - 1) {
                cy.get('button.save-settings')
                    .click().wait('@settings_free_field_api', {timeout: 80000});

                cy.get('.dataTables_length select').select(3, {force: true});
                for (let i = 1; i < typeLength; i++) {
                    cy.get(linesTableFreeFieldsComponent)
                        .contains(labelName + i, {timeout: 8000})
                        .should('exist');
                }
            }
        }
    })
})

Cypress.Commands.add('editFreeFieldInSettings', (settingsItemName) => {
    const labelName = Date.now().toString(36);
    cy.intercept('GET', '/parametrage/champs-libres/api/*').as('settings_free_field_api');
    cy.intercept('POST', '/filtre-sup/api').as('filter_get_by_page');
    cy.get('div.settings-item').its('length').then((count) => {
        if (count !== 1) {
            cy.get(`[data-menu=${settingsItemName}]`).eq(0).click();
        } else {
            cy.get(`${linesTableFreeFieldsComponent} td.dataTables_empty`, {timeout: 5000})
                .should('not.exist');
        }
    });

    cy.get(`[data-menu=${settingsItemName}]`)
        .then(($item) => {

            if ($item.find('button.edit-button').length) {
                cy.get(`[data-menu=${settingsItemName}] button.edit-button`)
                    .click().wait('@settings_free_field_api');
            } else {
                cy.get(linesTableFreeFieldsComponent).first().find('td').eq(2)
                    .click();
            }
        }).then(() => {
            cy.get(`${linesTableFreeFieldsComponent} input[name=label]`).first()
                .click()
                .clear().type(labelName);
    })

    cy.get(linesTableFreeFieldsComponent)
        .then(($element) => {

            if ($element.find('select[name=category]').length) {
                cy
                    .wrap($element).find('select[name=category]').first()
                    .select(1);
            }

        });

    cy.get(linesTableFreeFieldsComponent).first().find('input[type=checkbox]')
        .check({force: true});
    //TODO wait !!!
    cy.wait(2000);
    cy.get('button.save-settings')
        .click().wait('@settings_free_field_api', {timeout: 100000});
    cy.get(linesTableFreeFieldsComponent)
        .contains(labelName, {timeout: 8000})
        .should('exist');
})

Cypress.Commands.add('uncheckAllFixedFieldInSettings', (tableName) => {
    cy.intercept('GET', 'parametrage/champ-fixe/*').as('settings_fixed_field_api');
    cy.get('[data-menu=champs_fixes]').eq(0)
        .click().wait('@settings_fixed_field_api');
    cy.get(`[data-menu=champs_fixes] table[id=table-${tableName}-fixed-fields]`).find('input[type=checkbox]')
        .uncheck({force: true});
    cy.get('button.save-settings').click();
    cy.get(`[data-menu=champs_fixes] table[id=table-${tableName}-fixed-fields] input[type=checkbox]`)
        .should('not.be.checked');
})

Cypress.Commands.add('checkAllFixedFieldInSettings', (tableName) => {
    cy.intercept('GET', 'parametrage/champ-fixe/*').as('settings_fixed_field_api');
    cy.get('[data-menu=champs_fixes]').eq(0)
        .click().wait('@settings_fixed_field_api');
    cy.get(`[data-menu=champs_fixes] table[id=table-${tableName}-fixed-fields]`).find('input[type=checkbox]')
        .check({force: true});
    cy.get('button.save-settings').click();
    cy.get(`[data-menu=champs_fixes] table[id=table-${tableName}-fixed-fields] input[type=checkbox]`)
        .should('be.checked');
})


