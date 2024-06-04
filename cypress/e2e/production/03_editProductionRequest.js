import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');

describe('edit a production request', () => {
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

        cy.get('#tableProductions_filter').should('be.visible', {timeout: 8000}).then((div) => {
            cy.wrap(div)
                .find('input')
                .type(`P-2024053014430001{enter}`)
                .wait(1000);
        });

        cy.get('table#tableProductions tbody tr')
            .first()
            .find('td')
            .eq(1)
            .click()
            .wait('@production_operation_history_api');
    });

    it('should edit a production request', () => {
        const editProductionRequest = {
            comment: 'Cypress2000',
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
            cy.contains('.wii-field-name', key).parents('div.row').first().within(() => {
                cy.get('.wii-body-text').invoke('text').then((fieldValue) => {
                    expect(fieldValue.trim()).to.equal(expectedValue);
                });
            });
        });
    });

    it('Check if all free data is present', () => {
        const freeFields = {
            cypress: 'cypress2',
            yesNo: 'Oui',
            numeric: 200,
            selectMultiple: ['dfjdjdjdj', 'select multiple'],
            selectSimple: 'select',
        };

        Object.keys(freeFields).forEach((key) => {
            const expectedValue = freeFields[key];

            cy.contains('.wii-field-name', key).parents('.flex-column').within(() => {
                cy.get('.wii-body-text').invoke('text').then((fieldValue) => {
                    if (Array.isArray(expectedValue)) {
                        expect(expectedValue.join(", ")).to.equal(fieldValue.trim());
                    } else {
                        expect(expectedValue.toString()).to.equal(fieldValue.trim());
                    }
                });
            });
        });
    });
});
