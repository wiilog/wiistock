import routes, { interceptRoute } from "/cypress/support/utils/routes";
import {uncaughtException} from "/cypress/support/utils";
import {inProgress, toTreat, processed} from '/cypress/support/utils/statusConstants';

const user = Cypress.config('user');
const modalUpdateStatus = 'modalUpdateProductionRequestStatus';
const openModalUpdateProductionRequestStatus = 'div.open-modal-update-production-request-status';
const expectedAt = "2024-05-28";
const productionRequestCardColumn = `div.planning-col[data-card-selector=${expectedAt}]`;
describe('Test of the production request', () => {
    beforeEach(() => {
        uncaughtException();
        interceptRoute(routes.production_update_status);
        interceptRoute(routes.production_request_planning_api_test)
        cy.login(user);

        //Obligé d'intercepter toutes les requêtes car l'interception avec l'url spécifique ne marche pas
        cy.intercept('GET', "**", (req) => {
            if (req.url.toString().includes("/production/planning/api")) {
                const url = new URL(req.url, window.location.origin);
                url.searchParams.set('startDate', '2024-05-27');
                req.url = url.toString();
                req.continue();
            }
        });

        cy.visit('/production/planning/index');
    });

    it('The production request should be present with the informations', () => {
        const productionRequest = {
            'Numéro d\'OF': '1',
            'Code produit/article': '1',
            'Emplacement de dépose': 'BUREAU GT',
            'Quantité': '5',
            'Nombre de lignes': '1',
            'Numéro projet': '1',
        };

        cy
            .get(productionRequestCardColumn)
            .find('a.planning-card') //Recupération de la carte pour un jour donné.
            .then((card) => {
                cy
                    .wrap(card)
                    .find(openModalUpdateProductionRequestStatus)
                    .children()
                    .invoke('text')
                    .then((statusValue) => {
                        expect(statusValue.trim()).to.equal(toTreat);//Vérification du statut
                    });
                for (const key in productionRequest) {
                    cy
                        .wrap(card)
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
        cy
            .get(productionRequestCardColumn)
            .find('a.planning-card')
            .then((card) => {
                cy
                    .wrap(card)
                    .find(openModalUpdateProductionRequestStatus)
                    .click()
            });
        cy
            .get(`#${modalUpdateStatus}`).should('be.visible', {timeout: 8000})
            .then(() => {
                cy.select2Ajax('status', inProgress, modalUpdateStatus, 'production_request_planning_api_test', false);
                cy.closeAndVerifyModal(`#${modalUpdateStatus}`, 'submitEditUpdateStatusProductionRequest', 'production_request_planning_api_test', true);
                cy.wait(1000);
            });
        cy
            .get(productionRequestCardColumn)
            .find('a.planning-card')
            .then((card) => {
                cy
                    .wrap(card)
                    .find(openModalUpdateProductionRequestStatus)
                    .children()
                    .invoke('text')
                    .then((statusValue) => {
                        expect(statusValue.trim()).to.equal(inProgress);
                    });
            });
    });

    it('Change the status to processed', () => {
        cy
            .get(productionRequestCardColumn)
            .find('a.planning-card')
            .then((card) => {
                cy
                    .wrap(card)
                    .find(openModalUpdateProductionRequestStatus)
                    .click();
            });
        cy
            .get(`#${modalUpdateStatus}`).should('be.visible', {timeout: 8000})
            .then(() => {
                cy.select2Ajax('status', processed, modalUpdateStatus, 'production_request_planning_api_test', false);
                cy.closeAndVerifyModal(`#${modalUpdateStatus}`, 'submitEditUpdateStatusProductionRequest', 'production_request_planning_api_test', true);
                cy.wait(1000);
            });
    });
});
