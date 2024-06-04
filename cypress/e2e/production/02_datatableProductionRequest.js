import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');

describe('Verify if the datatable of production requests work', () => {
    beforeEach(() => {
        interceptRoute(routes.production_new);
        interceptRoute(routes.production_api);
        interceptRoute(routes.production_operation_history_api);

        cy.login(user);
        cy.visit('/');
    });

    it('Opening production request from datatable', () => {
        cy.navigateInNavMenu('menu-production', 'production_request_index');

        let productionRequestId;
        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .invoke("text")
            .then( (id) => {
                productionRequestId = id.trim();
            });

        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .click()
            .wait('@production_operation_history_api');

        cy.get(".dispatch-number ")
            .find(".wii-small-text")
            .invoke("text")
            .then((text) => {
                expect(text.trim()).to.equal(productionRequestId)
            });
    });
});
