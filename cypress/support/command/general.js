Cypress.Commands.add('navigateInNavMenu', (menu, subMenu) => {
    cy
        .get('nav')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(`[data-cy-container="${menu}"]`)
        .click()
        .then((element) => {
            if (subMenu === undefined) {
                return;
            }
            cy.get(`nav [data-cy-icon="${menu}"`)
                .parents('.dropdown-item-sub')
                .siblings('.dropdown-menu.dropdown-menu-sub')
                .should('be.visible')
                .get(`.dropdown-item[data-cy-nav-item="${subMenu}"]`).first()
                .click();
        })
})

Cypress.Commands.add('navigateInQuickMoreMenu', (request) => {
    cy
        .get('.quick-plus')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(`[data-cy-request-item="${request}"]`)
        .click()
        .then(() => {
            cy.wait(1000);
        });
})

Cypress.Commands.add('fillFreeFields', (freeFields) => {
    Object.keys(freeFields).forEach((key) => {
        const value = freeFields[key];

        cy.contains('.free-fields-container label', key).then($label => {
            const $inputOrSelect = $label.find('input, select, textarea');

            if ($inputOrSelect.length > 0) {
                const elementType = $inputOrSelect[0].tagName.toLowerCase();
                switch (elementType) {
                    case 'input':
                        if ($inputOrSelect.attr('type') === 'checkbox' || $inputOrSelect.attr('type') === 'radio') {
                            cy.wrap($inputOrSelect).each(($input) => {
                                cy.get(`label[for=${$input[0].id}] > span`).then(($inputLabel) => {
                                    if ($inputLabel.text().trim() === value) {
                                        cy.wrap($input).check({ force: true });
                                    }
                                });
                            });
                        } else {
                            cy.wrap($inputOrSelect).type(value, { force: true });
                        }
                        break;
                    case 'select':
                        if ($inputOrSelect.hasClass('list-multiple')) {
                            cy.select2($inputOrSelect.attr('name'), value);
                        } else {
                            cy.select2Ajax($inputOrSelect.attr('name'), value, '', true, '/select/*', true);
                        }
                        break;
                    case 'textarea':
                        cy.wrap($inputOrSelect).type(value, { force: true });
                        break;
                    default:
                        cy.log(`Type d'élément non supporté: ${elementType}`);
                }
            } else {
                cy.log(`Aucun champ trouvé pour le label: ${key}`);
            }
        });
    });
});
