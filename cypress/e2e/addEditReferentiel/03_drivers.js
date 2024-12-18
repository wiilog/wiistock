import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Chauffeurs', () => {
    beforeEach(() => {
        interceptRoute(routes.chauffeur_new);
        interceptRoute(routes.chauffeur_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'chauffeur_index');
        uncaughtException();
    })

    it('should add a new driver', () => {

        const driver = {
            nom: 'Eve',
            prenom: 'Adam',
            documentID: '12345632',
            carrier: 'DHL',
        }
        const propertiesMap = {
            'Nom': 'nom',
            'Prénom': 'prenom',
            'DocumentID': 'documentID',
            'Transporteur': 'carrier',
        }

        const selectorModal = '#modalNewChauffeur';

        // open modal
        cy.openModal(selectorModal,'nom');

        cy.get(`${selectorModal}`).should('be.visible', {timeout: 8000}).then(() => {

            // edit values
            cy.typeInModalInputs(selectorModal, driver, ['carrier']);
            // edit values select2
            cy.select2Ajax('transporteur', driver.carrier, '', '/select/carrier*')

            // close and verify modal is closed
            cy.closeAndVerifyModal(selectorModal, 'submitNewChauffeur', 'chauffeur_new');
        })

        cy.checkDataInDatatable(driver, 'nom', 'tableChauffeur_id', propertiesMap);
    })


    it('should edit a driver', () => {

        const driverToEdit = ['Chauffeur']
        const newDrivers = [{
            nom: 'Robinet',
            prenom: 'Pluviote',
            documentID: '666',
            carrier: 'DHL',
        }]
        const propertiesMap = {
            'Nom': 'nom',
            'Prénom': 'prenom',
            'DocumentID': 'documentID',
            'Transporteur': 'carrier',
        }

        const selectorModal = '#modalEditChauffeur';

        driverToEdit.forEach((driverToEditName, index) => {
            cy.clickOnRowInDatatable('tableChauffeur_id', driverToEditName);

            cy.get(`${selectorModal}`).should('be.visible');

            // edit values
            cy.typeInModalInputs(selectorModal, newDrivers[index], ['carrier']);

            // clear previous value
            cy.clearSelect2AjaxValues(`${selectorModal} [name="transporteur"]`);
            // refill select2
            cy.select2Ajax('transporteur', newDrivers[index].carrier, 'modalEditChauffeur', '/select/carrier*')

            // submit form & wait reponse
            cy.closeAndVerifyModal(selectorModal, 'submitEditChauffeur', 'chauffeur_edit');

            cy.checkDataInDatatable(newDrivers[index], 'nom', 'tableChauffeur_id', propertiesMap)
        })
    })
})
