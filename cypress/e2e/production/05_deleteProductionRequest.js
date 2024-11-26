import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Delete the production request', () => {
    beforeEach(() => {
        uncaughtException();
        interceptRoute(routes.production_edit);
        interceptRoute(routes.production_api);
        interceptRoute(routes.production_operation_history_api);
        interceptRoute(routes.production_status_history_api);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('menu-production', 'production_request_index');
    });

    it('Delete the production request', () => {
        let productionRequestId = "P-2024053014490001"
        const tableProductions = 'tableProductions'
        const columnNumberId = 2;

        cy.searchInDatatable('#tableProductions_filter', productionRequestId);

        cy
            .get(`table#${tableProductions} tbody tr`)
            .first()
            .find('td')
            .eq(columnNumberId)
            .invoke("text")
            .then( (id) => {
                expect(id.trim()).to.equal(productionRequestId);
            });

        cy
            .get(`table#${tableProductions} tbody tr`)
            .first()
            .find('td')
            .eq(columnNumberId)
            .click()
            .wait('@production_operation_history_api');

        cy.dropdownDroprightAction('Supprimer');

        cy.confirmModal();

        cy.searchInDatatable('#tableProductions_filter', productionRequestId);

        cy.checkDatatableIsEmpty(`#${tableProductions}`);
    });
});
