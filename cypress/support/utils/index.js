// Description: This file contains all the utils functions used in the tests

/*
 * This method allow to get the index of a column in a datatable by its name (th)
 * @param columnName : name of the column (in French)
 * @param tableId : id of the table to check
 * @param customId : id of the custom table (if the table is a custom table)
 * @param continueOnFind : boolean to continue the test even if you find the column (return array of index)
*/
export function getColumnIndexByColumnName(columnName, tableId) {
    return cy.get(`#${tableId} thead tr th`)
        .then((ths) => {
            const index = Array.from(ths).findIndex((th) => th.textContent.trim() === columnName);

            if (index === -1) {
                throw new Error(`Column ${columnName} not found`);
            }

            return index;
        });
}

/*
  * This method need to be used at the top of the test file to avoid uncaught exception in the test
 */
export function uncaughtException(){
    Cypress.on('uncaught:exception', (err, runnable) => {
        return false;
    });
}


/* This method allow to capitalize the first letter of a string
    * @param string : string to capitalize
 */

export function capitalizeFirstLetter(string)
{
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
}
