import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user= Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

const ULFreeFieldsLines = 'table[data-table-processing=fixedFields] tbody tr';

describe('Get the right permissions for logistic units arrivals', () => {
    beforeEach(() => {
        interceptRoute(routes.settings_save);
        interceptRoute(routes.settings_free_field_api);

        cy.login(user);
        cy.visit('/');
        cy.openSettingsItem('arrivages');
        uncaughtException();
    })

    it('should get the right permissions', () => {
        cy.get(`[data-menu=configurations]`)
            .eq(0)
            .first()
            .click();

        cy.get(`[data-menu=configurations] input[type=checkbox]`)
            .uncheck({force: true});
        cy.get(`[data-menu=configurations] [data-name=AUTO_PRINT_LU] input[type=checkbox]`)
            .check({force: true});
        cy.get(`[data-menu=configurations] [data-name=SEND_MAIL_AFTER_NEW_ARRIVAL] input[type=checkbox]`)
            .check({force: true});

        cy.get('[data-menu=configurations]').then(($item) => {
            const selects = [
                {
                    name: 'MVT_DEPOSE_DESTINATION',
                },
                {
                    name: 'DROP_OFF_LOCATION_IF_CUSTOMS',
                },
                {
                    name: 'DROP_OFF_LOCATION_IF_EMERGENCY',
                }
            ]

            selects.forEach((select) => {
                if ($item.find(`select[name=${select.name}]`).siblings('.select2').find('.select2-selection__clear').length) {
                    cy.get(`select[name=${select.name}]`)
                        .siblings('.select2')
                        .find('.select2-selection__clear')
                        .click();
                }
            })
        })

        cy.get('button.save-settings')
            .click();
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
