import routes, {interceptRoute} from "cypress/support/utils/routes";
const user= Cypress.config('user');
import {uncaughtException} from "cypress/support/utils";

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
            .first()
            .click();

        cy.checkDatatableCheckboxes("table-truck-arrival-fixed-fields", true);

        cy.get('button.save-settings')
            .click().wait('@settings_save');
    });

    it('should setup UL arrivals settings with truck arrival', () => {

        cy.openSettingsItem('arrivages');
        cy.get(`[data-menu=configurations]`)
            .first()
            .click();

        cy.get(`[data-menu=configurations] [data-name=USE_TRUCK_ARRIVALS] input[type=checkbox]`)
            .check({force: true});

        cy.get('button.save-settings')
            .click().wait('@settings_save');

        //
        cy.get(`[data-menu=champs_fixes]`)
            .first()
            .click();

        cy.checkDatatableCheckboxes("table-arrival-fixed-fields", false, ["Numéro tracking transporteur"]);

        cy.get('button.save-settings')
            .click().wait('@settings_save');
    });
});
