import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user= Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Get the right permissions for logistic units arrivals', () => {
    beforeEach(() => {
        interceptRoute(routes.settings_save);
        interceptRoute(routes.settings_free_field_api);

        cy.login(user);
        cy.visit('/');
        cy.openSettingsItem('arrivages');
        uncaughtException();
    });

    it('should get the right permissions', () => {
        cy.get(`[data-menu=configurations]`)
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
            });
        });

        cy.get('button.save-settings')
            .click();
        cy.get(`[data-menu=champs_fixes]`)
            .eq(0)
            .first()
            .click();

        cy.checkDatatableCheckboxes("table-arrival-fixed-fields", true);

        cy.get('button.save-settings')
            .click().wait('@settings_save');
    })
})
