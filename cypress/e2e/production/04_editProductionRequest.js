import routes, {interceptRoute} from "/cypress/support/utils/routes";
import {uncaughtException} from "/cypress/support/utils";
import {inProgress, toTreat, processed} from '/cypress/support/utils/statusConstants';

const user = Cypress.config('user');
const selectorUpdateModal = 'modalUpdateProductionRequestStatus';
const buttonUpdateStatus= '.open-modal-update-production-request-status';

describe('edit a production request', () => {
    beforeEach(() => {
        uncaughtException();
        interceptRoute(routes.production_edit);
        interceptRoute(routes.production_api);
        interceptRoute(routes.production_operation_history_api);
        interceptRoute(routes.production_status_history_api);
        interceptRoute(routes.production_update_status_content);
        interceptRoute(routes.production_update_status);

        cy.login(user);
        cy.visit('/production/voir/3');
    });

    it('should edit a production request', () => {
        const editProductionRequest = {
            comment: 'Cypress2000',
        };
        const selectorEditModal = 'modalEditProductionRequest';

        cy.get('.dropright.dropdown')
            .click()
            .find("span[title=Modifier]")
            .click();

        cy.get(`#${selectorEditModal}`).should('be.visible', { timeout: 8000 }).then(() => {
            cy.fillComment(`#${selectorEditModal} .ql-editor`, editProductionRequest.comment);
            cy.closeAndVerifyModal(`#${selectorEditModal}`, 'submitEditProductionRequest', 'production_edit',true);

        });

        cy.verifyComment(editProductionRequest.comment)
    });

    it('should change the status of production request to in progress', () => {
        cy.get(buttonUpdateStatus)
            .click();

        cy.get(`#${selectorUpdateModal}`).should('be.visible', { timeout: 8000 }).then(() => {
            cy.select2Ajax('status', inProgress, selectorUpdateModal);

            cy.closeAndVerifyModal(`#${selectorUpdateModal}`, 'submitEditUpdateStatusProductionRequest', 'production_operation_history_api',true);

            cy.verifyStatus(inProgress);
        });
    });

    it('should change the status of production request to treat', () => {
        cy.get(buttonUpdateStatus)
            .click();

        cy.get(`#${selectorUpdateModal}`).should('be.visible', { timeout: 8000 }).then(() => {
            cy.select2Ajax('status', toTreat, selectorUpdateModal);

            cy.closeAndVerifyModal(`#${selectorUpdateModal}`, 'submitEditUpdateStatusProductionRequest', 'production_operation_history_api',true);

            cy.verifyStatus(toTreat);
        });
    });


    it('should change the status of production request to processed', () => {
        cy.get(buttonUpdateStatus)
            .click();
        cy.get(`#${selectorUpdateModal}`).should('be.visible', { timeout: 8000 }).then(() => {
            cy.select2Ajax('status', processed, selectorUpdateModal);

            cy.closeAndVerifyModal(`#${selectorUpdateModal}`, 'submitEditUpdateStatusProductionRequest', 'production_operation_history_api',true);

            cy.get(buttonUpdateStatus).should('not.exist');

            cy.verifyStatus(processed);
        });
    });

    it('Check if all fixed data is present', () => {
        const productionRequestInformation = {
            manufacturingOrderNumber: '02',
            productArticleCode: '002',
            dropLocation: 'LABO 11',
            quantity: '2',
            lineCount: '20',
            projectNumber: '0002',
        };
        const propertiesMap = {
            'Numéro d\'OF': 'manufacturingOrderNumber',
            'Code produit/article': 'productArticleCode',
            'Emplacement de dépose': 'dropLocation',
            'Quantité': 'quantity',
            'Nombre de lignes': 'lineCount',
            'Numéro projet': 'projectNumber',
        };

        Object.keys(propertiesMap).forEach((key) => {
            const propertyKey = propertiesMap[key];
            const expectedValue = productionRequestInformation[propertyKey].toString();
            //Allows us to search the data of a production request against the array provided by searching for the key
            cy
                .contains('.wii-field-name', key)
                .parents('div.row')
                .first()
                .within(() => {
                    cy
                        .get('.wii-body-text')
                        .invoke('text')
                        .then((fieldValue) => {
                            expect(fieldValue.trim()).to.equal(expectedValue);
                        });
                });
        });
    });

    it('Check if all free data is present', () => {
        const freeFields = {
            cypress: 'cypress2',
            // Utilisation d'un string et non d'un booleen car la valeur peut avoir plus de deux valeurs possible.
            yesNo: 'Oui',
            numeric: 200,
            selectMultiple: ['choixmultiple1', 'choixmultiple2'],
            selectSimple: 'select',
        };

        Object.keys(freeFields).forEach((key) => {
            const expectedValue = freeFields[key];
            //Allows us to search the data free fields of a production request against the array provided by searching for the key
            cy
                .contains('.wii-field-name', key)
                .parents('.flex-column')
                .within(() => {
                    cy
                        .get('.wii-body-text')
                        .invoke('text')
                        .then((fieldValue) => {
                            if (Array.isArray(expectedValue)) {
                                //For the multiple select
                                expect(expectedValue.join(", ")).to.equal(fieldValue.trim());
                            } else {
                                expect(expectedValue.toString()).to.equal(fieldValue.trim());
                            }
                        });
                });
        });
    });
});
