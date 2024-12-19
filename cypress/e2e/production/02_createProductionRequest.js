import routes, {interceptRoute} from "/cypress/support/utils/routes";
import {uncaughtException} from "/cypress/support/utils";

const user = Cypress.config('user');
const selectorModal = 'modalNewProductionRequest';

describe('create a production request', () => {
    beforeEach(() => {
        uncaughtException();
        interceptRoute(routes.production_new);
        interceptRoute(routes.production_api);

        cy.login(user);
        cy.visit('/');
    });

    it('Opening production request creation modal from production request page', () =>{
        cy.navigateInNavMenu('menu-production', 'production_request_index');
        cy.navigateInQuickMoreMenu('production');
        cy.get(`#${selectorModal}`).should('be.visible', { timeout: 8000 });
    });

    it('Opening production request creation modal from home page', () =>{
        cy.navigateInQuickMoreMenu('production');
        cy.get(`#${selectorModal}`).should('be.visible', { timeout: 8000 });
    });

    it('should add a new production request', () => {
        cy.navigateInQuickMoreMenu('production');
        const date = new Date(Date.now()).toISOString();
        const newProductionRequest = {
            type: 'standard',
            manufacturingOrderNumber: '01',
            productArticleCode: '02',
            expectedAt: date.substring(0, 16),
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
        const freeFields = {
            cypress: 'cypressTest',
            // Utilisation d'un string et non d'un booleen car la valeur peut avoir plus de deux valeurs possible.
            yesNo: 'Oui',
            date: date.substring(0,10),
            numeric: 50,
            selectMultiple: ['dfjdjdjdj', 'select multiple'],
            selectSimple: 'select',
        };

        cy.get(`#${selectorModal}`).should('be.visible', { timeout: 8000 }).then(() => {
            // Type in the inputs
            cy.select2Ajax('type', newProductionRequest.type, selectorModal, '/select/*', false);
            cy.select2Ajax('dropLocation', newProductionRequest.dropLocation, selectorModal);
            cy.typeInModalInputs(`#${selectorModal}`, newProductionRequest, ['type', 'dropLocation', 'comment', 'file']);
            cy.fillFreeFields(freeFields);
            // comment
            cy.fillComment('.ql-editor', newProductionRequest.comment);
            // put file in the input
            cy.fillFileInput(`cypress/fixtures/${newProductionRequest.file}`)
            // Close and verify modal
            cy.closeAndVerifyModal(`#${selectorModal}`, 'submitNewProductionRequest', 'production_new', true);

            // Wait for the datatable to be reloaded
            cy.wait('@production_api');
            cy.wait(500);

            // Check data in datatablew
            cy.checkDataInDatatable(newProductionRequest, 'type', 'tableProductions', propertiesMap, ['expectedAt', 'cypress', 'comment', 'file']);
        });

        // Ensure modal is not visible
        cy.get(`#${selectorModal}`).should('not.be.visible');

    });
});
