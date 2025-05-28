import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Transporteurs', () => {
    beforeEach(() => {
        interceptRoute(routes.transporteur_api);
        interceptRoute(routes.transporteur_save);
        interceptRoute(routes.transporteur_save_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'transporteur_index');
        uncaughtException();
    })

    it('should add a new transporter', () => {

        const transporter = {
            label: 'MY LONG TRANSPORTEUR IS INSANE',
            code: 'WIILOG',
        }
        const propertiesMap = {
            'Nom': 'label',
            'Code': 'code',
        }
        // Store modal ID in a variable
        const selectorModal = '#modalTransporteur';
        // Trigger modal opening
        cy.openModal(selectorModal , 'label', '[data-cypress="newCarrier"]');

        // Wait for the modal to be visible
        cy.get(selectorModal).should('be.visible', { timeout: 8000 }).then(() => {
            // Edit values using custom command
            cy.typeInModalInputs(selectorModal, transporter);

            // Submit the form and wait for intercepts
            cy.closeAndVerifyModal(selectorModal, undefined, 'transporteur_api', true);
        });

        // Wait for the modal to close
        cy.get(selectorModal).should('not.be.visible');

        // Reload datatable and check after edit
        cy.wait('@transporteur_api');

        // Check datatable after edit
        cy.checkDataInDatatable(transporter, 'label', 'tableTransporteur_id', propertiesMap);
    })

    it('should edit a transporter', () => {

        const transporterToEdit = ['TRANSPORTEUR']
        const newTransporters = [{
            label: 'LA POSTE',
            code: 'LA POSTE',
        }]
        const propertiesMap = {
            'Nom': 'label',
            'Code': 'code',
        }

        const selectorModal = '#modalTransporteur';

        // load datatable
        cy.wait('@transporteur_api');

        transporterToEdit.forEach((transporterToEditName, index) => {
            // click on the row to edit
            cy.clickOnRowInDatatable('tableTransporteur_id', transporterToEditName);

            cy.get(`${selectorModal}`).should('be.visible');

            // edit values
            cy.typeInModalInputs(selectorModal, newTransporters[index]);

            // Submit the form and wait for intercepts
            cy.closeAndVerifyModal(selectorModal, undefined, 'transporteur_api', true);

            cy.checkDataInDatatable(newTransporters[index], 'label', 'tableTransporteur_id', propertiesMap)
        })
    })
})
