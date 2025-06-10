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
 * cy.checkAllInColumnManagement('.columnManagementButton', '#modalFieldModes', '#modalFieldModes');
 */
Cypress.Commands.add('checkAllInColumnManagement',(buttonSelector, dropdownSelector = '#modalFieldModes', modalSelector = '#modalFieldModes') => {
    // open the modal
    cy.get(`${buttonSelector} .dropdown-toggle`).click();
    cy.get(`${buttonSelector} [data-target='${dropdownSelector}']`).click();
    cy.get(modalSelector).should('be.visible');

    // check all input with checkbox types in the modal
    cy.get(`${modalSelector} input[type='checkbox']:not(:checked)`).each(($checkbox) => {
        cy.get('label[for=' + $checkbox.attr('id') + ']').click({force: true});
    });
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

/**
 * Allows us to fill the select fields in the filters field.
 * @param object Contains the data to fill the selects.
 * @param select2Name Name of selects without ajax request .
 */
Cypress.Commands.add('fillSelectsInFiltersField', (object, select2Name) => {
    cy
        .get('div.filters-wrapper-row > div.select-filter')
        .each(($select) => {
            if ($select.hasClass('statuses-filter')) {
                return;
            }
            const selectElement = $select.find('select').first();
            if (!selectElement) {
                return;
            }
            cy
                .wrap(selectElement)
                .invoke('attr', 'name')
                .then((name) => {
                    if (select2Name.includes(name)) {
                        cy.select2(name, object[name]);
                    } else {
                        cy.select2AjaxMultiple(name, [object[name]]);
                    }
                });

        });
});

/**
 * Allows us to fill inputs fields in the filters field.
 * @param data Contains the data to fill the inputs.
 */
Cypress.Commands.add('fillInputsInFiltersField', (data) => {
    cy
        .get('div.filters-wrapper-row > div[data-cypress=input-filter]')
        .each(($input) => {
            const inputElement = $input.find('input').first();
            if (!inputElement) {
                return;
            }
            cy
                .wrap(inputElement)
                .invoke('attr', 'name')
                .then((name) => {
                    cy.get(inputElement).type(data[name]);
                });
        });
});

/**
 * Allows us to check several checkboxes in a select which contains checkboxes.
 * @param nameOfSelect CSS selector of the select with checkboxes.
 * @param nameOfInputs CSS selector array to the different checkboxes to check in the select.
 */
Cypress.Commands.add('checkElementsInSelectInFiltersField', (nameOfSelect, nameOfInputs) => {
    cy
        .get(nameOfSelect)
        .find('button')
        .click();

    nameOfInputs.forEach( (nameInput) => {
        cy.get(nameInput).click();
    });
});

Cypress.Commands.add('checkAllorOneRowInSettingFixedFiled', (id, all = true, rowNames = []) => {

    const columnsToCheck = [];
    const rowIndex = []
    const columnsToCheckName = ["Afficher","Obligatoire"];
    const ULFixedFieldsLines = 'table[data-table-processing=fixedFields] tbody tr';

    // get the index of the columns with her name
    cy.get(`[id^=${id}] thead:first tr th`).then(($ths) => {
        $ths.each((index, th) => {
            if (columnsToCheckName.includes(th.textContent)) {
                // get the index of the columns to check in the datatable
                // -4 because the first 4 columns come from ??
                columnsToCheck.push(index - 4);
            }
        });

        if(all) {
            cy.get(`[data-menu=champs_fixes] input[type=checkbox]`)
                .uncheck({force: true});

            cy.get(ULFixedFieldsLines).each((tr) => {
                columnsToCheck.forEach((columnIndex) => {
                    cy.wrap(tr)
                        .find(`td:eq(${columnIndex}) input[type=checkbox]`)
                        .check({force: true});
                });
            });
        }else {

            let columnNumber = 0
            let cpt = 1;
            cy.get(`tbody>tr:eq(3)`).should("be.visible", {timeout: 8000}).then(() => {
                // get the index of the row with her name
                cy.get(`[id^=${id}] tbody tr:eq(3) td`).then((tds) => {
                    columnNumber = Array.from(tds).length;
                    cy.get(`[id^=${id}] tbody tr td`).each((td) => {
                        if (rowNames.includes(td.text().trim())) {
                            rowIndex.push(Math.trunc(cpt / (columnNumber)) + 2 );
                        }
                        cpt = cpt + 1;
                    }).then(() => {
                        rowIndex.forEach((index) => {
                            console.log(index)
                            columnsToCheck.forEach((columnIndex) => {
                                cy.get(`tbody>tr:eq(${index})`)
                                    .find(`td:eq(${columnIndex}) input[type=checkbox]`)
                                    .uncheck({force: true});
                            });

                            columnsToCheck.forEach((columnIndex) => {
                                cy.get(`tbody>tr:eq(${index})`)
                                    .find(`td:eq(${columnIndex}) input[type=checkbox]`)
                                    .check({force: true});
                            });
                        });
                    });
                });

            });
        }
    });
});


