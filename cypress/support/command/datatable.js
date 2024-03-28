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
                cy.wrap(td).parent('tr')
                    .find('td')
                    .eq(columnIndexes[objectProperty])
                    .contains(object[objectProperty]?.toString());
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
