Cypress.Commands.add('select2Ajax', (selectName, value, modalName = '', shouldClick = true, requestAlias = '/select/*', shouldWait = true) => {
    cy.intercept('GET', requestAlias).as(`${requestAlias}Request`);

    let getName;
    if (modalName !== '') {
        getName = `#${modalName} [name=${selectName}]`;
    } else {
        getName = `[name=${selectName}]`
    }

    if (shouldClick) {
        cy.get(getName)
            .siblings('.select2')
            .click()
            .parents()
            .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
            .type(value)
            .then(() => {
                if (shouldWait) {
                    cy
                        .wait(`@${requestAlias}Request`, {timeout: 20000})
                        .its('response.statusCode').should('eq', 200)
                }
            })
    } else {
        cy.get(getName)
            .siblings('.select2')
            .parents()
            .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
            .type(value)
            .then(() => {
                if (shouldWait) {
                    cy
                        .wait(`@${requestAlias}Request`, {timeout: 20000})
                        .its('response.statusCode').should('eq', 200)
                }
            })
    }
    cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .parents('.select2-dropdown')
        .find('.select2-results__option')
        .should('be.visible', {timeout: 6000})
        .first()
        .click({waitForAnimations: false, force: true})
        .then(() => {
            cy.get(getName).find('option:selected').should('have.length', 1);
        });
})

Cypress.Commands.add('select2AjaxMultiple', (selectName, value, modalName = '') => {
    cy.intercept('GET', '/select/*').as('select2Request');

    let getName;
    if (modalName !== '') {
        getName = `#${modalName} [name=${selectName}]`;
    } else {
        getName = `[name=${selectName}]`
    }

    value.forEach(element => {
        cy.get(getName)
            .siblings('.select2')
            .click()
            .type(element)
            .wait('@select2Request')
            .its('response.statusCode').should('eq', 200)
            .then(() => {
                cy.get('.select2-dropdown')
                    .find('.select2-results__option')
                    .first()
                    .click({waitForAnimations: false, multiple: true})
            })
    })

    cy.get(getName).find('option:selected').should('have.length', value.length)
})

Cypress.Commands.add('select2', (selectName, value) => {
    const select = cy.get(`[name=${selectName}]`);

    if (!Array.isArray(value)) {
        value = [value];
    }

    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click()
            .type(`${element}{enter}`)
    })

    cy.get(`[name=${selectName}]`).then(($select) => {
        if ($select.hasOwnProperty('multiple')) {
            select.find('option').should('have.length', value.length)
        } else {
            select.find('option:selected').should('have.length', value.length)
        }
    })
})

/**
 * @description: This command removes previous select2 values from a dropdown based on the specified name.
 * @param {string} selectName : The name attribute of the select dropdown.
 * @example :
 * cy.removePreviousSelect2Values('locations');
 */
Cypress.Commands.add('removePreviousSelect2Values', (selectName) => {
    // todo : bug if select2 have more than 1 value
    cy.get(`select[name=${selectName}]`).as('select');
    cy.get('@select')
        .siblings('.select2')
        .find('.select2-selection__choice__remove')
        .click();
});

/**
 * @description: This command removes previous select2 ajax values from a dropdown based on the specified name.
 * @param {string} selectName : The name attribute of the select dropdown.
 * @example :
 * cy.removePreviousSelect2AjaxValues('locations');
 */
Cypress.Commands.add('removePreviousSelect2AjaxValues', (selectName) => {
    cy.get(`select[name=${selectName}]`)
        .siblings('.select2')
        .find('.select2-selection__clear')
        .click();
})
