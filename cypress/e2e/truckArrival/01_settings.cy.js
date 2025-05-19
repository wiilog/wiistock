import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user= Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

const ULFreeFieldsLines = 'table[data-table-processing=fixedFields] tbody tr';

describe('Setup settings', () => {
    beforeEach(() => {
        interceptRoute(routes.settings_save);
        interceptRoute(routes.settings_free_field_api);

        cy.login(user);
        cy.visit('/');
        uncaughtException();
    })

    it('should have all fixed fields truck arrivals checked', () => {
        cy.openSettingsItem('arrivages_camion');
        cy.get(`[data-menu=champs_fixes]`)
            .eq(0)
            .first()
            .click();

        // check the table has at least one line
        cy.get(ULFreeFieldsLines)
            .find('td', {timeout: 10000})
            .should('have.length.gt', 1);
        // uncheck all the checkboxes
        cy.get(`[data-menu=champs_fixes] input[type=checkbox]`)
            .uncheck({force: true});


        const columnsToCheck = [];
        const columnsToCheckName = ["Afficher","Obligatoire"];

        // get the index of the columns with her name
        cy.get('[id^="table-truck-arrival-fixed-fields"] thead:first tr th').then(($ths) => {
            $ths.each((index, th) => {
                if (columnsToCheckName.includes(th.textContent)) {
                    // get the index of the columns to check in the datatable
                    // -4 because the first 4 columns come from ??
                    columnsToCheck.push(index - 4);
                }
            });
        })

        cy.get(ULFreeFieldsLines).each((tr) => {
            columnsToCheck.forEach((columnIndex) => {
                cy.wrap(tr)
                    .find(`td:eq(${columnIndex}) input[type=checkbox]`)
                    .check({force: true});
            });
        });

        cy.get('button.save-settings')
            .click().wait('@settings_save');
    })

    it('should setup UL arrivals settings with truck arrival', () => {

        cy.openSettingsItem('arrivages');
        cy.get(`[data-menu=configurations]`)
            .eq(0)
            .first()
            .click();

        cy.get(`[data-menu=configurations] [data-name=USE_TRUCK_ARRIVALS] input[type=checkbox]`)
            .check({force: true});

        cy.get('button.save-settings')
            .click().wait('@settings_save');

        //
        cy.get(`[data-menu=champs_fixes]`)
            .eq(0)
            .first()
            .click();

        cy.get(ULFreeFieldsLines)
            .find('td', {timeout: 10000})
            .should('have.length.gt', 1);
        cy.get(`[data-menu=champs_fixes] input[type=checkbox]`)
            .uncheck({force: true});

        const columnsToCheck = [];
        const columnsToCheckName = ["Afficher","Obligatoire"];

        // get the index of the columns with her name
        cy.get('[id^="table-arrival-fixed-fields"] thead:first tr th').then(($ths) => {
            $ths.each((index, th) => {
                if (columnsToCheckName.includes(th.textContent)) {
                    // get the index of the columns to check in the datatable
                    // -4 because the first 4 columns come from ??
                    columnsToCheck.push(index - 4);
                }
            });
        })

        cy.get(ULFreeFieldsLines).each((tr) => {
            columnsToCheck.forEach((columnIndex) => {
                    cy.wrap(tr)
                        .find(`td:eq(${columnIndex}) input[type=checkbox]`)
                        .check({force: true});
            });
        });

        cy.get('button.save-settings')
            .click().wait('@settings_save');
    })
})
