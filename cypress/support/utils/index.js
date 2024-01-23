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
