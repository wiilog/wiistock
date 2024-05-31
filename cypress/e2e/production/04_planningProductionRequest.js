import routes, { interceptRoute } from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import '@4tw/cypress-drag-drop'
import 'cypress-real-events'

describe('Test of the production request', () => {
    beforeEach(() => {
        interceptRoute(routes.production_update_status);
        interceptRoute(routes.production_request_planning_api_test)
        cy.login(user);

        //Obligé d'intercepter toutes les requêtes car l'interception avec l'url spécifique ne marche pas
        cy.intercept('GET', "**", (req) => {
            if (req.url.toString().includes("/production/planning/api?date=")) {
                const url = new URL(req.url, window.location.origin);
                url.searchParams.set('date', '2024-05-27');
                req.url = url.toString();
                req.continue();
            }
        });

        cy.visit('/production/planning/index');
    });

    it('The production request should be present with the informations', () => {
        const status = 'A Traiter';
        const expectedAt = "2024-05-28";
        const productionRequest = {
            'Numéro d\'OF': '1',
            'Code produit/article': '1',
            'Emplacement de dépose': 'BUREAU GT',
            'Quantité': '5',
            'Nombre de lignes': '1',
            'Numéro projet': '1',
            'Pièces jointes': 'Oui',
            'yesNo': 'Non',
        };

        cy.get(`div.production-request-card-column[data-date=${expectedAt}]`).find('a.planning-card')
            .then((card) => {
                cy.wrap(card)
                    .find('div.open-modal-update-production-request-status')
                    .children()
                    .invoke('text')
                    .then((statusValue) => {
                        expect(statusValue.trim()).to.equal(status);
                    });
                for (const key in productionRequest) {
                    cy.wrap(card)
                        .find(`div[data-field-label="${key}"]`)
                        .children()
                        .invoke('text')
                        .then((text) => {
                            expect(productionRequest[key]).to.equal(text.trim());
                        });
                }
            });
    });

    it('Change the status to in progress', () => {
        const expectedAt = "2024-05-28";
        const inProgress = "En cours";

        cy.get(`div.production-request-card-column[data-date=${expectedAt}]`).find('a.planning-card')
            .then((card) => {
                cy.wrap(card)
                    .find('div.open-modal-update-production-request-status')
                    .click()
            });
        cy.log(routes.production_request_planning_api_test.route )
        cy.get('#modalUpdateProductionRequestStatus').should('be.visible', {timeout: 8000}).then(() => {
            cy.select2Ajax('status', inProgress, 'modalUpdateProductionRequestStatus', true, 'production_request_planning_api_test', false);
            cy.closeAndVerifyModal('#modalUpdateProductionRequestStatus', 'submitEditUpdateStatusProductionRequest', 'production_request_planning_api_test', true);
            cy.wait(1000);
        });
        cy.get(`div.production-request-card-column[data-date=${expectedAt}]`).find('a.planning-card')
                .then((card) => {
                    cy.wrap(card)
                        .find('div.open-modal-update-production-request-status')
                        .children()
                        .invoke('text')
                        .then((statusValue) => {
                            expect(statusValue.trim()).to.equal(inProgress);
                        });
                });
    });

    it('Change the status to processed', () => {
        const expectedAt = "2024-05-28";
        const processed = "Traité";

        cy.get(`div.production-request-card-column[data-date=${expectedAt}]`).find('a.planning-card')
            .then((card) => {
                cy.wrap(card)
                    .find('div.open-modal-update-production-request-status')
                    .click()
            });
        cy.log(routes.production_request_planning_api_test.route )
        cy.get('#modalUpdateProductionRequestStatus').should('be.visible', {timeout: 8000}).then(() => {
            cy.select2Ajax('status', processed, 'modalUpdateProductionRequestStatus', true, 'production_request_planning_api_test', false);
            cy.closeAndVerifyModal('#modalUpdateProductionRequestStatus', 'submitEditUpdateStatusProductionRequest', 'production_request_planning_api_test', true);
            cy.wait(1000);
        });
        cy.get(`div.production-request-card-column[data-date=${expectedAt}]`).find('a.planning-card')
            .then((card) => {
                cy.wrap(card)
                    .find('div.open-modal-update-production-request-status')
                    .children()
                    .invoke('text')
                    .then((statusValue) => {
                        expect(statusValue.trim()).to.equal(processed);
                    });
            });
    });
});
