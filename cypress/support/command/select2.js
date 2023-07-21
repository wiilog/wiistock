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
        .first()
        .click({waitForAnimations: false, multiple: true})
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
