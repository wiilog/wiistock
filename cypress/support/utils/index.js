/*
 * This method allow to get the index of a column in a datatable by its name (th)
 * @param columnName : name of the column (in French)
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
