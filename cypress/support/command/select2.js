
Cypress.Commands.add('select2Ajax', (selectName, value) => {
    cy.intercept('GET', '/select/*').as('select2Request');
    cy.get(`[name=${selectName}]`)
        .siblings('.select2')
        .click()
        .parents()
        .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .type(value)
        .wait('@select2Request', {timeout: 10000})
        .its('response.statusCode').should('eq', 200)
        .then(() => {
            cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
                .parents('.select2-dropdown')
                .find('.select2-results__option')
                .first()
                .click({waitForAnimations: false, multiple: true})
                .then(() => {
                    cy.get(`[name=${selectName}]`).find('option:selected').should('have.length', 1);
                });
        })
})

Cypress.Commands.add('select2AjaxMultiple', (selectName, value) => {
    cy.intercept('GET', '/select/*').as('select2Request');
    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click()
            .parents()
            .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
            .type(element)
            .wait('@select2Request')
            .its('response.statusCode').should('eq', 200)
    })

    cy.get(`[name=${selectName}]`).find('option').should('have.length', value.length)
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
