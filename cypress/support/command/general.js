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
    cy.get('.free-fields-container label').each(($label) => {

        const labelText = $label.find('.field-label').text().replace(/\*/, '').trim();
        if (labelText in freeFields) {
            const value = freeFields[labelText];
            const $inputOrSelect = $label.find('input, select, textarea');

            if ($inputOrSelect.length > 0) {
                const elementType = $inputOrSelect[0].tagName.toLowerCase();
                switch (elementType) {
                    case 'input':
                        if ($inputOrSelect.length > 1) {
                            cy.wrap($inputOrSelect).each(($input) => {
                                cy.get(`label[for=${$input[0].id}] > span`).then(($label) => {
                                    if ($label.text().trim() === value) {
                                        cy.get(`#${$input[0].id}`).check({ force: true });
                                    }
                                });
                            })
                        } else{
                            cy.wrap($inputOrSelect).type(value);
                        }
                        break;
                    case 'select':
                        if($inputOrSelect.hasClass("list-multiple")) {
                            cy.select2($inputOrSelect.attr("name"), value);
                        }
                        else{
                            cy.select2Ajax($inputOrSelect.attr("name"), value, '', true, '/select/*', true);
                        }
                        break;
                    case 'textarea':
                        cy.wrap($inputOrSelect).type(value);
                        break;
                    default:
                        cy.log(`Type d'élément non supporté: ${elementType}`);
                }
            }
        }
    });
});


