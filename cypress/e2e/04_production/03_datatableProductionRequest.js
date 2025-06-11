import routes, {interceptRoute} from "/cypress/support/utils/routes";
import {uncaughtException} from "/cypress/support/utils";

const user = Cypress.config('user');
const tableProduction = "tableProductions";

describe('Check if the line where we clicked opens the correct production request', () => {
    beforeEach(() => {
        uncaughtException();
        interceptRoute(routes.production_new);
        interceptRoute(routes.production_api);
        interceptRoute(routes.production_operation_history_api);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('menu-production', 'production_request_index');
    });

    it('Opening production request from datatable', () => {
        const columnNumberId = 2;
        let productionRequestId;
        cy.get(`table#${tableProduction} tbody tr`)
            .first()
            .find('td')
            .eq(columnNumberId)
            .invoke("text")
            .then( (id) => {
                productionRequestId = id.trim();
            });

        cy.get(`table#${tableProduction} tbody tr`)
            .first()
            .find('td')
            .eq(columnNumberId)
            .click()
            .wait('@production_operation_history_api');

        cy.get(".dispatch-number ")
            .find(".wii-small-text")
            .invoke("text")
            .then((text) => {
                expect(text.trim()).to.equal(productionRequestId)
            });
    });

    it('Search and open a production request with the search bar', () => {
        const columnNumberId = 2;
        const fabricationOrderNumber = 'P-2024053014430001';

        cy.searchInDatatable('#tableProductions_filter', fabricationOrderNumber);

        cy.get(`table#${tableProduction} tbody tr`)
            .first()
            .find('td')
            .eq(columnNumberId)
            .invoke("text")
            .then( (text) => {
                expect(fabricationOrderNumber).to.equal(text.trim());
            });
    });
});
