import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Emplacements', () => {
    beforeEach(() => {
        interceptRoute(routes.emplacement_api);
        interceptRoute(routes.emplacements_groupes_api);
        interceptRoute(routes.zones_api);
        interceptRoute(routes.emplacement_new);
        interceptRoute(routes.location_form_new);
        interceptRoute(routes.location_form_edit);
        interceptRoute(routes.emplacement_edit);
        interceptRoute(routes.location_group_new);
        interceptRoute(routes.location_group_edit);
        interceptRoute(routes.zone_new);
        interceptRoute(routes.zone_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'emplacement_index');
        uncaughtException();
    })

    it('should add a new location', () => {
        const selectorModal = '#modalNewLocation';
        const newLocation = {
            name: 'STOCK',
            description: 'Non défini',
            zone: 'Activité Standard',
        }
        const propertiesMap = {
            'Nom': 'name',
            'Description': 'description',
            'Zone': 'zone',
        }

        cy.get(`button[data-cy-name="new-location-button"]`).should('be.visible')
            .click()
            .wait('@location_form_new');

        cy.get(selectorModal).should('be.visible', { timeout: 4000 }).then(() => {
            // Type in the inputs
            cy.typeInModalInputs(selectorModal, newLocation, ['zone']);

            cy.select2Ajax('zone', newLocation.zone);

            // Close and verify modal
            cy.closeAndVerifyModal(selectorModal, null, 'emplacement_new',true);

            // Wait for the datatable to be reloaded
            cy.wait('@emplacement_api');

            // Check data in datatable
            cy.checkDataInDatatable(newLocation, 'name', 'locationsTable', propertiesMap);
        });

        // Ensure modal is not visible
        cy.get(selectorModal).should('not.be.visible');

    });

    it('should edit a location', () => {
        cy.wait('@emplacement_api');

        const locationToEdit = ['EMPLACEMENT', 'ZONE 41']

        const newLocations = [{
            name: 'ZONE 007',
            description: 'Cypress',
            zone: 'Activité Standard',
        }, {
            name: 'LABO 666',
            description: 'Cypress',
            zone: 'Activité Standard',
        }]

        const propertiesMap = {
            'Nom': 'name',
            'Description': 'description',
            'Zone': 'zone',
        }

        if (newLocations.length !== locationToEdit.length) {
            throw new Error('The number of locations to edit and the number of new locations are different')
        }

        const editLocation = (locationToEditName, newLocationData) => {
            const selectorModal = '#modalEditLocation';
            // Click on the location to edit and wait for modal charging
            cy.clickOnRowInDatatable('locationsTable', locationToEditName);
            cy
                .wait('@location_form_edit')
                .wait(700);
            cy.get(selectorModal).should('be.visible');

            // Edit values
            cy.typeInModalInputs(selectorModal, newLocationData, ['zone']);
            cy.select2Ajax('zone', newLocationData.zone);

            // Submit the form
            cy.closeAndVerifyModal(selectorModal, null, 'emplacement_edit', true);
            cy.wait('@emplacement_api');

            // Check all data in datatable are correct after edit
            cy.checkDataInDatatable(newLocationData, 'name', 'locationsTable', propertiesMap);
        };

        locationToEdit.forEach((locationToEditName, index) => {
            editLocation(locationToEditName, newLocations[index]);
        });
    })

    it('should add a new group', () => {
        let newLocationGroup = {
            label: 'TOTO',
            description: 'Non défini',
            status: true,
            locations: ['ZONE 007'],
        }
        const propertiesMap = {
            'Nom': 'label',
            'Description': 'description',
            'Actif / Inactif': 'status',
            'Nombre emplacements': 'locations',
        }

        // Press the "Groupes" menu
        const menu = 'Groupes';
        cy.get('.nav-item').find('a').contains(menu).click();

        // Store selectorModal in a variable
        const selectorModal = '#modalNewLocationGroup';

        cy.get(`button[data-toggle='modal']`).filter(':visible').click();

        cy.get(selectorModal).should('be.visible', { timeout: 8000 }).then(() => {
            cy.get(`${selectorModal} input[name=label]`).should('be.visible').then(() => {
                // Edit values using custom command
                cy.typeInModalInputs(selectorModal, newLocationGroup, ['description', 'status', 'locations']);
                cy.get(`${selectorModal} [name=description]`).type(newLocationGroup.description);

                // Toggle button status
                const statusValue = newLocationGroup.status ? "1" : "0";
                cy.checkCheckbox(selectorModal, `[data-title='Statut'] input`, statusValue);

                // todo Refactor this part with the object newLocationGroup -> it doesn't work with newLocationGroup.locations :(
                cy.select2AjaxMultiple('locations', ['ZONE 007']);

                cy.get(`${selectorModal} button#submitNewLocationGroup`).click().wait(['@location_group_new', '@emplacements_groupes_api']);
            });
        });

        cy.get(selectorModal).should('not.be.visible');
        cy.wait('@emplacements_groupes_api');

        // Change the number of locations to match with the datatable value
        newLocationGroup = { ...newLocationGroup, locations: newLocationGroup.locations.length, status: newLocationGroup.status ? 'Inactif' : 'Actif' };
        cy.checkDataInDatatable(newLocationGroup, 'label', 'groupsTable', propertiesMap);
    })

    it('should edit a group', () => {
        // Store selectorModal in a variable
        const selectorModal = '#modalEditLocationGroup';
        const locationGroupToEdit = ['TOTO', 'GROUPE']
        const newLocationGroups = [{
            label: 'TATA',
            description: 'Cypress',
            status: 'Inactif',
            locations: ['LABO 666'],
        }, {
            label: 'GROUPE 007',
            description: 'Cypress',
            status: 'Inactif',
            locations: ['LABO 666'],
        }]
        const propertiesMap = {
            'Nom': 'label',
            'Description': 'description',
            'Actif / Inactif': 'status',
            'Nombre emplacements': 'locations',
        }

        if (newLocationGroups.length !== locationGroupToEdit.length) {
            throw new Error('The number of groups to edit and the number of new groups are different')
        }

        // Press the "Groupes" menu
        const menu = 'Groupes'
        cy.get('.nav-item').find('a').contains(menu).click();

        // Wait for the datatable to be loaded before clicking on the row
        cy.wait('@emplacements_groupes_api').then(() => {
            locationGroupToEdit.forEach((locationGroupToEditName, index) => {
                cy.clickOnRowInDatatable('groupsTable', locationGroupToEditName);

                // Ensure the modal is visible
                cy.get(selectorModal).should('be.visible');

                // Edit values using custom command
                cy.typeInModalInputs(selectorModal, newLocationGroups[index], ['description', 'status', 'locations']);
                cy.get(`${selectorModal} [name=description]`).type(newLocationGroups[index].description);

                // Toggle button status
                const statusValue = newLocationGroups[index].status ? "1" : "0";
                cy.checkCheckbox(selectorModal, `[data-title='Statut'] input`, statusValue);

                cy.clearSelect2('locations', "modalEditLocationGroup");

                cy.select2AjaxMultiple('locations', newLocationGroups[index].locations, 'modalEditLocationGroup', false);

                // Submit form
                cy.closeAndVerifyModal(selectorModal, 'submitEditLocationGroup', 'location_group_edit');

                // Reload datatable
                cy.wait('@emplacements_groupes_api');

                // Check datatable after edit
                newLocationGroups[index] = {
                    ...newLocationGroups[index],
                    locations: newLocationGroups[index].locations.length,
                    status: newLocationGroups[index].status ? 'Inactif' : 'Actif'
                }
                cy.checkDataInDatatable(newLocationGroups[index], 'label', 'groupsTable', propertiesMap);
            });
        });
    })

    it('should add a new area', () => {

        // Define new area details
        let newArea = {
            name: 'ZONE 007',
            description: 'Non défini',
            active: false,
        };

        const propertiesMap = {
            'Nom': 'name',
            'Description': 'description',
            'Actif': 'active',
        };

        // Press the "Zones" menu
        const menu = 'Zones';
        cy.get('.nav-item').find('a').contains(menu).click();

        // Store modal ID in a variable
        const selectorModal = '#modalNewZone';

        // Trigger modal opening
        cy.openModal(selectorModal);

        // Wait for the modal to be visible
        cy.get(selectorModal).should('be.visible', { timeout: 8000 }).then(() => {
            // Edit values using custom command
            cy.typeInModalInputs(selectorModal, newArea, ['description','active']);
            cy.get(`${selectorModal} [name=description]`).type(newArea.description);

            // Edit toggle button status using custom command
            const statusValue = newArea.status ? "1" : "0";
            // todo : refactor avec la méthode checkCheckbox
            cy.get(`#modalNewZone [data-title='Statut*'] input[value="${statusValue}"]`).click({force: true});

            // Submit the form and wait for intercepts
            cy.closeAndVerifyModal(selectorModal, undefined, 'zones_api', true);
        });

        // Wait for API response
        cy.wait('@zones_api');

        // Change the boolean value to match with the datatable value
        newArea = { ...newArea, active: newArea.active ? 'Oui' : 'Non' };

        // Check all data in datatable are correct after edit
        cy.checkDataInDatatable(newArea, 'name', 'zonesTable', propertiesMap);
    })

    it('should edit an area', () => {

        // Define area details for editing
        const areaToEdit = ['ZONE'];
        let newAreas = [{
            name: 'ZONE NORD',
            description: 'Non défini',
            active: true,
        }];

        const propertiesMap = {
            'Nom': 'name',
            'Description': 'description',
            'Actif': 'active',
        };

        // Press the "Zones" menu
        const menu = 'Zones';
        cy.get('.nav-item').find('a').contains(menu).click();

        // Wait for the datatable to be loaded
        cy.wait('@zones_api');

        // Store modal ID in a variable
        const selectorModal = '#modalEditZone';

        areaToEdit.forEach((areaToEditName, index) => {
            // Click on the row to edit
            cy.clickOnRowInDatatable('zonesTable', areaToEditName);
            cy.get(selectorModal).should('be.visible');

            // Edit values using custom command
            cy.typeInModalInputs(selectorModal, newAreas[index], ['active', 'description']);
            cy.get(`${selectorModal} [name=description]`).type(newAreas[index].description);

            // Edit toggle button status using custom command
            // todo : refactor avec la méthode checkCheckbox
            const statusValue = newAreas[index].active ? "1" : "0";
            cy.get(`${selectorModal} [data-title='Statut*'] input[value="${statusValue}"]`).click({force: true});

            // Submit the form and wait for intercepts
            cy.closeAndVerifyModal(selectorModal, undefined, 'zones_api', true);

            // Change the boolean value to match with the datatable value
            newAreas[index] = { ...newAreas[index], active: newAreas[index].active ? 'Oui' : 'Non' };
            cy.checkDataInDatatable(newAreas[index], 'name', 'zonesTable', propertiesMap);
        });

    })
})
