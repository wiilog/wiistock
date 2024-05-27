import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Véhicules', () => {
    beforeEach(() => {
        interceptRoute(routes.vehicle_edit);
        interceptRoute(routes.vehicule_api);
        interceptRoute(routes.vehicule_new);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'vehicle_index');
        uncaughtException();
    })

    it('should add a new vehicle', () => {
        const newVehicle = {
            registrationNumber: 'CL-010-RA',
        }
        const propertiesMap = {
            'Immatriculation': 'registrationNumber',
        }
        const selectorModal = '#modalNewVehicle';
        cy.openModal(selectorModal, 'registrationNumber');

        cy.get('#modalNewVehicle').should('be.visible', {timeout: 8000}).then(() => {

            // edit values
            cy.typeInModalInputs(selectorModal, newVehicle);
            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitNewVehicle', 'vehicule_new', undefined, '.modal-footer button.submit' );
        })

        cy.wait('@vehicule_api');

        cy.checkDataInDatatable(newVehicle, 'registrationNumber', 'vehicleTable_id', propertiesMap);
    })

    it('should edit a vehicle', () => {

        const vehicleToEdit = ['VEHICULE']
        const newVehicles = [{
            registrationNumber: 'AA-000-AA',
        }]
        const propertiesMap = {
            'Immatriculation': 'registrationNumber',
        }
        const selectorModal = '#modalEditVehicle';
        cy.wait('@vehicule_api');

        vehicleToEdit.forEach((vehicleToEditName, index) => {
            cy.clickOnRowInDatatable('vehicleTable_id', vehicleToEditName);

            cy.get(selectorModal).should('be.visible');

            // edit values
            cy.typeInModalInputs(selectorModal, newVehicles[index]);

            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitEditVehicle', 'vehicle_edit');
            cy.wait('@vehicule_api');

            cy.checkDataInDatatable(newVehicles[index], 'registrationNumber', 'vehicleTable_id', propertiesMap)
        })
    })
})
