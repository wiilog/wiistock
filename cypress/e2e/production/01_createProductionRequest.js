import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');

describe('create a production request', () => {
    beforeEach(() => {
        interceptRoute(routes.production_new);
        interceptRoute(routes.production_api);

        cy.login(user);
        cy.visit('/');
    });

    it('Opening production request creation modal from production request page', () =>{
        cy.navigateInNavMenu('menu-production', 'production_request_index');
        cy.navigateInQuickMoreMenu('production');
        cy.get('#modalNewProductionRequest').should('be.visible', { timeout: 8000 });
    });

    it('Opening production request creation modal from home page', () =>{
        cy.navigateInQuickMoreMenu('production');
        cy.get('#modalNewProductionRequest').should('be.visible', { timeout: 8000 });
    });

    it('should add a new production request', () => {
        cy.navigateInQuickMoreMenu('production');
        const selectorModal = '#modalNewProductionRequest';
        const date = new Date(Date.now()).toISOString();
        const newProductionRequest = {
            type: 'standard',
            manufacturingOrderNumber: '01',
            productArticleCode: '02',
            //TODO voir pour la date par rapport au format de l'utilisateur.
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
            yesNo: 'Oui',
            date: date.substring(0,10),
            numeric: 50,
            selectMultiple: ['dfjdjdjdj', 'select multiple'],
            selectSimple: 'select',
        };

        cy.get(selectorModal).should('be.visible', { timeout: 8000 }).then(() => {
            // Type in the inputs
            //TODO : Voir Pourquoi shouldWait doit être à false pour type le wait ne récupère pas la réponse.
            cy.select2Ajax('type', newProductionRequest.type, 'modalNewProductionRequest', true, '/select/*', false);
            cy.select2Ajax('dropLocation', newProductionRequest.dropLocation, 'modalNewProductionRequest');
            cy.typeInModalInputs(selectorModal, newProductionRequest, ['type', 'dropLocation', 'comment', 'file']);
            cy.fillFreeFields(freeFields);
            // comment
            cy.get('.ql-editor')
                .click()
                .type(newProductionRequest.comment);
            // put file in the input
            cy.get('input[type=file]')
                .selectFile(`cypress/fixtures/${newProductionRequest.file}`, {force: true});

            // Close and verify modal
            cy.closeAndVerifyModal(selectorModal, 'submitNewProductionRequest', 'production_new',true);

            // Wait for the datatable to be reloaded
            cy.wait('@production_api');
            cy.wait(500);

            // Check data in datatablew
            cy.checkDataInDatatable(newProductionRequest, 'type', 'tableProductions', propertiesMap, ['expectedAt', 'cypress', 'comment', 'file']);
        });

        // Ensure modal is not visible
        cy.get(selectorModal).should('not.be.visible');

    });
});
