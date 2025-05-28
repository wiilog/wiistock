import {defaultTypeSpeed} from '../utils/cypressConfigConstants';

Cypress.Commands.add('select2Ajax', (selectName, value, modalName = '', requestAlias = '/select/*', shouldWait = true, newOne = false) => {
    cy.intercept('GET', requestAlias).as(`${requestAlias}Request`);

    const selectorPrefix = modalName !== '' ? `#${modalName} ` : '';
    const getName = `${selectorPrefix}[name=${selectName}]`;
    const inputSearchSelector = `input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`;

    // open select2 drowdown with search input
    cy.get(getName).siblings('.select2.select2-container').find('.select2-selection')
        .click()
        .wait(200);

    cy.get(inputSearchSelector)
        .type(value)
        .then(() => {
            if (shouldWait) {
                cy
                    .wait(`@${requestAlias}Request`, {timeout: 20000})
                    .its('response.statusCode').should('eq', 200)
            }
        })

    if(newOne){
        cy.get('.create-new-container')
            .should('be.visible', {timeout: 6000})
            .click({waitForAnimations: false, force: true})
            .then(() => {
                cy.get(getName).find('option:selected').should('have.length', 1);
            });
    }else{
        cy.get(inputSearchSelector)
            .closest('.select2-dropdown')
            .should('be.visible', {timeout: 6000})
            .contains(value)
            .should('be.visible')
            .click({waitForAnimations: false, force: true})
            .then(() => {
                cy.get(getName).find('option:selected').should('have.length', 1);
            });
    }
})

Cypress.Commands.add('select2AjaxMultiple', (selectName, value, modalName = '', ajax = true) => {
    cy.intercept('GET', '/select/*').as('select2Request');

    let getName;
    if (modalName !== '') {
        getName = `#${modalName} [name=${selectName}]`;
    } else {
        getName = `[name=${selectName}]`
    }

    value.forEach(element => {
        const test =
            cy.get(getName)
            .siblings('.select2')
            .first()
            .click()
            .type(element)

            if (ajax) {
                test.wait('@select2Request')
                    .its('response.statusCode').should('eq', 200)
                    .wait(100)
            }

            cy.get('.select2-dropdown')
                .find('.select2-results__option')
                .contains(element)
                .should('be.visible')
                .first()
                .click({waitForAnimations: false, multiple: true})

    })

    cy.get(getName).find('option:selected').should('have.length', value.length)
})

Cypress.Commands.add('select2', (selectName, value, customDelay = null, forceMultiple = false ) => {
    const select = cy.get(`[name=${selectName}]`);

    if (!Array.isArray(value)) {
        value = [value];
    }

    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click()
            .wait(200)
            .type(`${element}`, {delay: customDelay ?? defaultTypeSpeed})
            .wait(200)
            .get('.select2-dropdown')
            .contains(element)
            .should('be.visible')
            .first()
            .click();
    });

    cy.get(`[name=${selectName}]`).then(($select) => {
        select.find('option:selected').should('have.length', value.length)
    })
})

/**
 * @description: This command removes previous select2 values from a dropdown based on the specified name.
 * @param {string} selectName : The name attribute of the select dropdown.
 * @param {string} modalId : The ID of the modal containing the select dropdown.
 * @example :
 * cy.clearSelect2('locations', 'totoId);
 */
Cypress.Commands.add('clearSelect2', (selectName, modalId = null) => {
    cy.get(`#${modalId}`).then(($modal) => {
        if ($modal.find(`[name=${selectName}]`).siblings('.select2')
            .find('li .select2-selection__choice__remove').length) {
            cy.get(`[name=${selectName}]`)
                .siblings('.select2')
                .find('li .select2-selection__choice__remove')
                .then(($elements) => {
                    const numElements = $elements.length;
                    for (let i = 0; i < numElements; i++) {
                        cy.get(`[name=${selectName}]`)
                            .siblings('.select2')
                            .find('li .select2-selection__choice__remove')
                            .eq(0)
                            .click({force: true});
                    }
                });
        }
    })
});

/**
 * @description: This command removes previous select2 ajax values from a dropdown based on the specified name.
 * @param {string} selectName : The name attribute of the select dropdown.
 * @example :
 * cy.removePreviousSelect2AjaxValues('locations');
 */
Cypress.Commands.add('clearSelect2AjaxValues', (selector) => {
    cy.get(selector)
        .siblings('.select2')
        .find('.select2-selection__clear, .select2-selection__choice__remove')
        .each(($el) => {
            cy.get(selector)
                .siblings('.select2')
                .find('.select2-selection__clear, .select2-selection__choice__remove')
                .first()
                .click();
        })

    // hide select2 dropdown
    cy.get(selector).parent().click();
})
