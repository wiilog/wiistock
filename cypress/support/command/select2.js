import {defaultTypeSpeed} from '../utils/constants';

Cypress.Commands.add('select2Ajax', (selectName, value, modalName = '', shouldClick = true, requestAlias = '/select/*', shouldWait = true) => {
    cy.intercept('GET', requestAlias).as(`${requestAlias}Request`);

    const selectorPrefix = modalName !== '' ? `#${modalName} ` : '';
    const getName = `${selectorPrefix}[name=${selectName}]`;

    const select = cy.get(getName).siblings('.select2')

    if(shouldClick){
        select.click()
    }
    select.parents()
        .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .type(value)
        .then(() => {
            if (shouldWait) {
                cy
                    .wait(`@${requestAlias}Request`, {timeout: 20000})
                    .its('response.statusCode').should('eq', 200)
            }
        })

    cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .parents('.select2-dropdown')
        .find('.select2-results__option')
        .should('be.visible', {timeout: 6000})
        .contains(value)
        .should('be.visible')
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
                    .contains(element)
                    .should('be.visible')
                    .first()
                    .click({waitForAnimations: false, multiple: true})
            })
    })

    cy.get(getName).find('option:selected').should('have.length', value.length)
})

Cypress.Commands.add('select2', (selectName, value, customDelay = null) => {
    const select = cy.get(`[name=${selectName}]`);

    if (!Array.isArray(value)) {
        value = [value];
    }

    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click()
            .wait(100)
            .type(`${element}`, {delay: customDelay ?? defaultTypeSpeed})
            .find('.select2-results__option')
            .contains(value)
            .should('be.visible')
            .first()
    });

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
Cypress.Commands.add('clearSelect2AjaxValues', (selectName) => {
    cy.get(`select[name=${selectName}]`)
        .siblings('.select2')
        .find('.select2-selection__clear')
        .click();
})
