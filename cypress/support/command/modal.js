/**
 * @description: This command types values into selected inputs inside a modal based on an object.
 * @param {string} modalId : The ID selector of the modal.
 * @param {object} dataObject : An object containing the data to type into the inputs.
 * @param {array} excludedAttributes : An optional array containing the keys to exclude from typing in the modal inputs.
 * @example :
 * cy.typeInModalInputs('#modalNewFournisseur', {
 *    name: 'RENAULT',
 *    code: 'RENAULT',
 *    possibleCustoms: true,
 *    urgent: true,
 * }, ['possibleCustoms', 'urgent']);
 */
Cypress.Commands.add('typeInModalInputs', (modalId, dataObject, excludedAttributes = []) => {
    Object.keys(dataObject).forEach((propertyName) => {
        // Check if the property should be excluded
        if (!excludedAttributes.includes(propertyName)) {
            cy.get(`${modalId} input[name=${propertyName}]`).clear().type(dataObject[propertyName]);
        }
    });
});

/**
 * @description: This command checks or unchecks a checkbox inside a modal based on the provided boolean value.
 * @param {string} modalId : The ID selector of the modal.
 * @param {string} checkboxName : The name attribute of the checkbox.
 * @param {boolean} check : A boolean indicating whether the checkbox should be checked (true) or unchecked (false).
 * @example :
 * cy.checkCheckbox('#modalNewFournisseur', 'possibleCustoms', true);
 * cy.checkCheckbox('#modalNewFournisseur', 'urgent', false);
 */
Cypress.Commands.add('checkCheckbox', (modalId, checkboxName, check) => {
    const checkboxInput = cy.get(modalId).find(`${checkboxName}`);

    if (check) {
        checkboxInput.check().should('be.checked');
    } else {
        checkboxInput.uncheck().should('not.be.checked');
    }
});

/**
 * @description: This command opens a modal and checks if a specified input is visible.
 * @param {string} modalId : The ID selector of the modal.
 * @param {string} inputName : The name attribute of the input to check visibility (default is 'name').
 * @param {string} customSelectorBtn : The custom selector of the button to open the modal (default is null).
 * @example :
 * cy.openModal('#modalNewFournisseur');
 * cy.openModal('#modalNewFournisseur', 'customInputName');
 */
Cypress.Commands.add('openModal', (modalId, inputName = 'name', customSelectorBtn = null) => {
    customSelectorBtn ? cy.get(`${customSelectorBtn}`).click() : cy.get(`[data-target='${modalId}']`).click();
    customSelectorBtn ? cy.get(`${customSelectorBtn}`).click({force:true}) : cy.get(`[data-target='${modalId}']`).click({force:true});
    cy.get(modalId).find(`input[name=${inputName}]`).should('be.visible');
});

/**
 * @description: This command closes a modal by clicking a submit button and verifies the response using an interceptor.
 * @param {string} modalId : The ID selector of the modal.
 * @param {string} [submitButtonId='submitNewFournisseur'] : The ID of the submit button inside the modal.
 * @param {string} [interceptorAlias='supplier_new'] : The alias of the interceptor to wait for.
 * @param {boolean} [searchWithSubmitType=false] : If true, search for submit button based on type=submit attribute.
 * @param {string} [customId=null] : Custom submit button ID to use instead of the default.
 * @example :
 * cy.closeAndVerifyModal('#modalNewFournisseur');
 * cy.closeAndVerifyModal('#modalNewFournisseur', 'customSubmitButtonId', 'customInterceptorAlias', true);
 */
Cypress.Commands.add('closeAndVerifyModal', (modalId, submitButtonId, interceptorAlias, searchWithSubmitType = false, customId = null) => {
    let buttonSelector = `${modalId} button#${submitButtonId}`;

    // If searchWithSubmitType is true, search for submit button based on type=submit attribute
    if (searchWithSubmitType) {
        buttonSelector = `${modalId} button[type=submit]`;
    }

    // If customId is provided, use it as the button selector
    if (customId) {
        buttonSelector = customId;
    }

    cy.get(buttonSelector).click().wait(`@${interceptorAlias}`).then((xhr) => {
        expect(xhr.response.statusCode).to.equal(200);
    });

    cy.get(modalId).should('not.be.visible');
});


/**
 * @description: This command checks the visibility of a modal and its specified child element.
 * @param {string} modalId : The ID selector of the modal to check.
 * @param {string} childElementSelector : The child element selector within the modal to check visibility.
 * @example :
 * cy.checkModalVisibility('#modalNewFournisseur', 'input[name=name]');
 * // This command checks if the modal with ID 'modalNewFournisseur' is visible and if the input with name 'name' inside the modal is also visible.
 */
Cypress.Commands.add('checkModalVisibility', (modalId, childElementSelector) => {
    cy.get(modalId)
        .should('be.visible')
        .find(childElementSelector)
        .should('be.visible');
});

/**
 * This command allows selecting an option from a dropdown <select> element within a modal.
 * @param {string} modalId - The ID selector of the modal.
 * @param {string} name - The name attribute of the <select> element.
 * @param {string} value - The value of the option to be selected.
 * @param {string|null} customId - Optional. The ID selector of a custom container where the <select> element resides.
 *                                 If provided, the command will search for the <select> element within this container instead of the modal.
 *                                 If not provided, the command will search for the <select> element within the modal.
 * @example
 * // Select an option with the value "option1" from a dropdown with the name "dropdown" within the modal with ID "#myModal"
 * cy.select('#myModal', 'dropdown', 'option1');
 *
 * // Select an option with the value "option2" from a dropdown with the name "customDropdown" within a custom container with ID "#customContainer"
 * cy.select(null, 'customDropdown', 'option2', '#customContainer');
 */
Cypress.Commands.add('selectInModal', (modalId, name, value, customId = null) => {
    const select = customId ? cy.get(`${customId} select[name=${name}]`) : cy.get(`${modalId} select[name=${name}]`);
    select.select(value, { force: true });
});
