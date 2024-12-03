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
 * @param {string} [customSelector=null] : Custom submit button selector to use instead of the default.
 * @example :
 * cy.closeAndVerifyModal('#modalNewFournisseur');
 * cy.closeAndVerifyModal('#modalNewFournisseur', 'customSubmitButtonId', 'customInterceptorAlias', true);
 */
Cypress.Commands.add('closeAndVerifyModal', (modalId, submitButtonId, interceptorAlias, searchWithSubmitType = false, customSelector = null) => {
    // If searchWithSubmitType is true, search for submit button based on type=submit attribute
    let buttonSelector;

    if (searchWithSubmitType) {
        buttonSelector = `${modalId} button[type=submit]`;
    } else if (customSelector) {
        // If customId is provided, use it as the button selector
        buttonSelector = customSelector;
    } else {
        buttonSelector = `${modalId} button#${submitButtonId}`;
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

/**
 * Allows us to fill all types of free fields.
 * @param freeFields Free fields to fill with the label of the input as key and the value as value.
 */
Cypress.Commands.add('fillFreeFields', (freeFields) => {
    Object.keys(freeFields).forEach((key) => {
        const value = freeFields[key];

        cy.contains('.free-fields-container label', key).then($label => {
            const $inputOrSelect = $label.find('input, select, textarea');

            if ($inputOrSelect.length > 0) {
                const elementType = $inputOrSelect[0].tagName.toLowerCase();
                switch (elementType) {
                    case 'input':
                        fillInputsRadioCheckbox($inputOrSelect, value);
                        break;
                    case 'select':
                        fillSelect($inputOrSelect, value);
                        break;
                    case 'textarea':
                        fillTextarea($inputOrSelect, value);
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

/**
 * Fill an input element.
 * @param $input Input to fill.
 * @param value Value to put in input.
 */
function fillInputsRadioCheckbox($input, value){
    const type = $input.attr('type');
    if (type === 'checkbox' || type === 'radio') {
        cy.wrap($input).each(($input) => {
            cy.get(`label[for=${$input[0].id}] > span`).then(($inputLabel) => {
                if ($inputLabel.text().trim() === value) {
                    cy.wrap($input).check({ force: true });
                }
            });
        });
    } else {
        cy.wrap($input).type(value, { force: true });
    }
}

/**
 * Fill a textarea element.
 * @param $textarea Textarea to fill.
 * @param value Value to put in the textarea.
 */
function fillTextarea($textarea, value){
    cy.wrap($textarea).type(value, { force: true });
}

/**
 * Allows us to fill a comment field.
 * @param selector Selector of the comments.
 * @param comment Comment to type in comment field.
 */
Cypress.Commands.add('fillComment', (selector, comment) => {
    cy
        .get(selector)
        .click()
        .clear()
        .wait(200)
        .type(comment);
})

/**
 * Allows us to fill a file input.
 * @param path path of the file.
 */
Cypress.Commands.add('fillFileInput', (path) => {
    cy.get('input[type=file]').selectFile(path, {force: true});
})

/**
 * Allows us to confirm a modal like the delete modal.
 */
Cypress.Commands.add('confirmModal', () => {
    cy
        .get("#confirmation-modal")
        .find("button[name=request]")
        .click();
})

/**
 * Fill a select element.
 * @param $select Select to fill.
 * @param value Value to put in the select.
 */
function fillSelect($select, value){
    if ($select.hasClass('list-multiple')) {
        cy.select2($select.attr('name'), value);
    } else {
        cy.select2Ajax($select.attr('name'), value, '', true, '/select/*', true);
    }
}
