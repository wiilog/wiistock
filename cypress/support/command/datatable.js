import {getColumnIndexByColumnName} from "../utils";

/**
 * @description: This command allow to check if the data in the datatable are correct or not
 * @param {object} object : object containing the data to check, use to create & check after
 * @example :
 * const suplier = {
 *             name: 'RENAULT', /!\ important to find the row
 *             code: 'RENAULT',
 *             possibleCustoms: 'oui',
 *             urgent: 'oui',
 *         };
 * @param {string} tableId : id of the table to check
 * @param {array} excludedKeys : array containing the keys to exclude from the check in datatable (if supplier object need 'name' but is not in the datatable)
 * @param {Map} propertiesMap : map containing the properties to check
 * @example :
 * const propertiesMap = {
 *                 'Nom': 'name',
 *                 'Code fournisseur': 'code',
 *                 'Possible douane': 'possibleCustoms',
 *                 'Urgent': 'urgent',
 *             };
 * Where the key is the name of the column in the datatable and the value is the property of the object
 */
Cypress.Commands.add('checkDataInDatatable', (object, objectId = 'label', tableId, propertiesMap, excludeKeys = []) => {
    cy.get(`#${tableId} tbody td`).contains(object[objectId]).then((td) => {
        const columnIndexes = {};

        // Get the index of each column
        Object.keys(propertiesMap).forEach((propertyName) => {
            const objectProperty = propertiesMap[propertyName];

            getColumnIndexByColumnName(propertyName, tableId).then((index) => {
                columnIndexes[objectProperty] = index;
            });
        });

        // Filter out keys to exclude
        const expectedKeys = Object.keys(object).filter(key => !excludeKeys.includes(key));

        cy.wrap(columnIndexes).should('have.keys', expectedKeys).then(() => {
            // Use the indexes to check the values
            Object.keys(columnIndexes).forEach((objectProperty) => {
                cy.log(`Checking ${objectProperty} with value ${object[objectProperty]}`)
                cy.wrap(td).invoke("prop","tagName").then((tagNane) => {
                    let newTd =  tagNane==="TD" ? cy.wrap(td).parent('tr') : cy.wrap(td).parent('td').parent('tr');
                    newTd.find('td')
                        .eq(columnIndexes[objectProperty])
                        .contains(object[objectProperty]?.toString());
                })
            });
        });
    });
})

/**
 * @description: This command allow to click on a row in a datatable by its label
 * @param {string} tableId : id of the table to check
 * @param {string} label : label of the row to click on
 */
Cypress.Commands.add('clickOnRowInDatatable', (tableId, label) => {
    cy.get(`#${tableId} tbody td`).contains(label).click();
})

/**
 * @description: This command allow to check all the columns in the column management modal
 * @param {string} buttonSelector : selector of the button (button & dropdown)
 * @param {string} dropdownSelector : selector of the dropdown
 * @param {string} modalSelector : selector of the modal
 * @example :
 * cy.checkAllInColumnManagement('.columnManagementButton', '#modalColumnVisible', '#modalColumnVisible');
 */
Cypress.Commands.add('checkAllInColumnManagement',(buttonSelector, dropdownSelector = '#modalColumnVisible', modalSelector = '#modalColumnVisible') => {
    // open the modal
    cy.get(`${buttonSelector} .dropdown-toggle`).click();
    cy.get(`${buttonSelector} [data-target='${dropdownSelector}']`).click();
    cy.get(modalSelector).should('be.visible');

    // check all input with checkbox types in the modal
    cy.get(`${modalSelector} input[type='checkbox']`).check({force: true, multiple: true});
    cy.get(`${modalSelector} input[type='checkbox']`).should('be.checked');

    // submit the modal & close it
    cy.get(`${modalSelector} button[type='submit']`).click();
    cy.get(modalSelector).should('not.be.visible');
})

/**
 * Allows us to search in a datatable.
 * @param selector Id of the div who contains the input.
 * @param value Value to search.
 */
Cypress.Commands.add('searchInDatatable', (selector, value) => {
    cy
        .get(selector).should('be.visible', {timeout: 8000}).then((div) => {
        cy
            .wrap(div)
            .find('input')
            .type(`${value}{enter}`)
            .wait(1000);
    });
})

/**
 * Allows us to check if the datatable is empty.
 * @param selector The selector of the datatable.
 */
Cypress.Commands.add('checkDatatableIsEmpty', (selector) => {
    cy
        .get(selector)
        .find('.dataTables_empty')
        .should("be.visible");
})
