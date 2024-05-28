import routes, {interceptRoute} from "/cypress/support/utils/routes";
import {wrap} from "regenerator-runtime";
const user = Cypress.config('user');

describe('Delete the production request', () => {
    beforeEach(() => {
        interceptRoute(routes.production_edit);
        interceptRoute(routes.production_api);
        interceptRoute(routes.production_operation_history_api);
        interceptRoute(routes.production_status_history_api);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('menu-production', 'production_request_index');
    });

    it('Delete the production request', () => {
        let productionRequestId;
        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .invoke("text")
            .then( (id) => {
                productionRequestId = id.trim();
            });

        cy.get('#tableProductions_filter').should('be.visible', {timeout: 8000}).then((div) => {
            cy.wrap(div)
                .find('input')
                .type(`${productionRequestId}{enter}`);
        });

        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .invoke("text")
            .then( (id) => {
                expect(id.trim()).to.equal(productionRequestId);
            });

        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .click()
            .wait('@production_operation_history_api');

        cy.get('.dropright.dropdown')
            .click()
            .find("span[title=Supprimer]")
            .click()
            .wait(200);

        cy.get("#confirmation-modal")
            .find("button[name=request]")
            .click();

        cy.get('#tableProductions_filter').should('be.visible', {timeout: 8000}).then((div) => {
           cy.wrap(div)
               .find('input')
               .type(`${productionRequestId}{enter}`);
        });

        cy.get('#tableProductions')
            .find('.dataTables_empty')
            .should("be.visible");
    });
});
