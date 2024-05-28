import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');

describe('Add a new production request', () => {
    beforeEach(() => {
        interceptRoute(routes.production_edit);
        interceptRoute(routes.production_api);
        interceptRoute(routes.production_operation_history_api);
        interceptRoute(routes.production_status_history_api);
        interceptRoute(routes.production_update_status_content);
        interceptRoute(routes.production_update_status);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('menu-production', 'production_request_index');

        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .click()
            .wait('@production_operation_history_api');
    });

    it('should edit the last production request', () => {
        const editProductionRequest = {
            comment: 'Cypress2',
        };

        cy.get('.dropright.dropdown')
            .click()
            .find("span[title=Modifier]")
            .click();

        cy.get('#modalEditProductionRequest').should('be.visible', { timeout: 8000 }).then(() => {
            cy.get('#modalEditProductionRequest .ql-editor')
                .click()
                .clear()
                .type(editProductionRequest.comment);

            cy.closeAndVerifyModal('#modalEditProductionRequest', 'submitEditProductionRequest', 'production_edit',true);

        });

        cy.get('.comment-container')
            .find('p')
            .invoke('text')
            .then((text) => {
                expect(text).to.equal(editProductionRequest.comment)
            });
    });

    it('should change the status of production request to in progress', () => {
        const inProgress = "En cours";

        cy.get('.open-modal-update-production-request-status')
            .click();
        cy.get('#modalUpdateProductionRequestStatus').should('be.visible', { timeout: 8000 }).then(() => {
            cy.select2Ajax('status', inProgress, 'modalUpdateProductionRequestStatus');

            cy.closeAndVerifyModal('#modalUpdateProductionRequestStatus', 'submitEditUpdateStatusProductionRequest', 'production_operation_history_api',true);

            cy.get('.timeline')
                .find('strong')
                .invoke('text')
                .then((text) => {
                    expect(text).to.equal(inProgress)
                });
        });
    });

    it('should change the status of production request to treat', () => {
        const toTreat = "A Traiter";

        cy.get('.open-modal-update-production-request-status')
            .click();
        cy.get('#modalUpdateProductionRequestStatus').should('be.visible', { timeout: 8000 }).then(() => {
            cy.select2Ajax('status', toTreat, 'modalUpdateProductionRequestStatus');

            cy.closeAndVerifyModal('#modalUpdateProductionRequestStatus', 'submitEditUpdateStatusProductionRequest', 'production_operation_history_api',true);

            cy.get('.timeline')
                .find('strong')
                .invoke('text')
                .then((text) => {
                    expect(text).to.equal(toTreat)
                });
        });
    });


    it('should change the status of production request to processed', () => {
        const processed = "Traité";

        cy.get('.open-modal-update-production-request-status')
            .click();
        cy.get('#modalUpdateProductionRequestStatus').should('be.visible', { timeout: 8000 }).then(() => {
            cy.select2Ajax('status', processed, 'modalUpdateProductionRequestStatus');

            cy.closeAndVerifyModal('#modalUpdateProductionRequestStatus', 'submitEditUpdateStatusProductionRequest', 'production_operation_history_api',true);

            cy.get('.open-modal-update-production-request-status').should('not.exist');

            cy.get('.timeline')
                .find('strong')
                .invoke('text')
                .then((text) => {
                    expect(text).to.equal(processed)
                });
        });
    });

    it('Check if all fixed data is present', () => {
        const productionRequestInformation = {
            type: 'standard',
            manufacturingOrderNumber: '01',
            productArticleCode: '02',
            dropLocation: 'BUREAU GT',
            quantity: 10,
            lineCount: 1,
            projectNumber: '03',
            comment: 'Cypress',
            file: 'logo.jpg',
        };
        const propertiesMap = {
            'Type': 'type',
            'Numéro d\'OF': 'manufacturingOrderNumber',
            'Code produit/article': 'productArticleCode',
            'Emplacement de dépose': 'dropLocation',
            'Quantité': 'quantity',
            'Nombre de lignes': 'lineCount',
            'Numéro projet': 'projectNumber',

        };

        cy.get('.details-header').find('.row').each((row) => {
            cy.wrap(row).find('.wii-field-name').invoke("text").then( (key) => {
                cy.wrap(row).find('.wii-body-text').invoke('text').then( (value) => {
                    if(key !== "Date de création" && key !== "Date attendue"){
                        expect(productionRequestInformation[propertiesMap[key]].toString()).to.equal(value.trim());
                    }
                });
            });
        });
    });

    it('Check if all free data is present', () => {
        const freeFields = {
            cypress: 'cypressTest',
            yesNo: 'Oui',
            numeric: 50,
            selectMultiple: ['dfjdjdjdj', 'select multiple'],
            selectSimple: 'select',
        };

        cy.get('.no-gutters').find('.flex-column').each((row) => {
            cy.wrap(row).find('.wii-field-name').invoke("text").then( (key) => {
                cy.wrap(row).find('.wii-body-text').invoke('text').then( (value) => {
                    if (key === "selectMultiple") {
                        expect(freeFields[key][0]+", "+freeFields[key][1]).to.equal(value.trim());
                    }
                    else if (key !== "date") {
                        expect(freeFields[key].toString()).to.equal(value.trim());
                    }
                });
            });
        });
    });
});
