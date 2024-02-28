import {uncaughtException} from "../support/utils";
import routes, {interceptRoute} from "../support/utils/routes";
const user = Cypress.config('user');

describe('Setup the environment', () => {
    it('Reset the db', () => {
        cy.startingCypressEnvironnement('$FTP_HOST')
        uncaughtException();
    });
})

describe('Add and edit components in Referentiel > Emplacements', () => {
    beforeEach(() => {
        interceptRoute(routes.emplacement_api);
        interceptRoute(routes.emplacements_groupes_api);
        interceptRoute(routes.zones_api);
        interceptRoute(routes.emplacement_new);
        interceptRoute(routes.location_api_new);
        interceptRoute(routes.emplacement_edit);
        interceptRoute(routes.location_group_new);
        interceptRoute(routes.location_group_edit);
        interceptRoute(routes.zone_new);
        interceptRoute(routes.zone_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'emplacement_index');
    })

    it('should add a new location', () => {
        const selectorModal = '#modalNewEmplacement';
        const newLocation = {
            label: 'STOCK',
            description: 'Non défini',
            zone: 'Activité Standard',
        }
        const propertiesMap = {
            'Nom': 'label',
            'Description': 'description',
            'Zone': 'zone',
        }

        cy.get(`button[data-cy-name="new-location-button"]`).should('be.visible')
            .click()
            .wait('@location_api_new');

        cy.get(selectorModal).should('be.visible', { timeout: 4000 }).then(() => {
            // Type in the inputs
            cy.typeInModalInputs(selectorModal, newLocation, ['zone']);

            cy.select2Ajax('zone', newLocation.zone);

            // Close and verify modal
            cy.closeAndVerifyModal(selectorModal, 'submitNewEmplacement', 'emplacement_new');

            // Wait for the datatable to be reloaded
            cy.wait('@emplacement_api');

            // Check data in datatable
            cy.checkDataInDatatable(newLocation, 'label', 'locationsTable', propertiesMap);
        });

        // Ensure modal is not visible
        cy.get(selectorModal).should('not.be.visible');

    });

    it('should edit a location', () => {
        cy.wait('@emplacement_api');

        const locationToEdit = ['EMPLACEMENT', 'ZONE 41']

        const newLocations = [{
            label: 'ZONE 007',
            description: 'Cypress',
            zone: 'Activité Standard',
        }, {
            label: 'LABO 666',
            description: 'Cypress',
            zone: 'Activité Standard',
        }]

        const propertiesMap = {
            'Nom': 'label',
            'Description': 'description',
            'Zone': 'zone',
        }

        if (newLocations.length !== locationToEdit.length) {
            throw new Error('The number of locations to edit and the number of new locations are different')
        }

        const editLocation = (locationToEditName, newLocationData) => {
            const selectorModal = '#modalEditEmplacement';
            // Click on the location to edit
            cy.clickOnRowInDatatable('locationsTable', locationToEditName);
            cy.get(selectorModal).should('be.visible');

            // Edit values
            cy.typeInModalInputs(selectorModal, newLocationData, ['zone']);
            cy.select2Ajax('zone', newLocationData.zone);

            // Submit the form
            cy.closeAndVerifyModal(selectorModal, 'submitEditEmplacement', 'emplacement_edit');
            cy.wait('@emplacement_api');

            // Check all data in datatable are correct after edit
            cy.checkDataInDatatable(newLocationData, 'label', 'locationsTable', propertiesMap);
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
        cy.wait('@emplacements_groupes_api');

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
            newLocationGroups[index] = { ...newLocationGroups[index], locations: newLocationGroups[index].locations.length, status: newLocationGroups[index].status ? 'Inactif' : 'Actif' }
            cy.checkDataInDatatable(newLocationGroups[index], 'label', 'groupsTable', propertiesMap);
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

describe('Add and edit components in Referentiel > Transporteurs', () => {
    beforeEach(() => {
        interceptRoute(routes.transporteur_api);
        interceptRoute(routes.transporteur_save);
        interceptRoute(routes.transporteur_save_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'transporteur_index');
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



describe('Add and edit components in Referentiel > Chauffeurs', () => {
    beforeEach(() => {
        interceptRoute(routes.chauffeur_new);
        interceptRoute(routes.chauffeur_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'chauffeur_index');
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
            cy.select2Ajax('transporteur', driver.carrier, '', true, '/select/carrier*')

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
            cy.clearSelect2AjaxValues('transporteur');
            // refill select2
            cy.select2Ajax('transporteur', newDrivers[index].carrier, 'modalEditChauffeur', false, '/select/carrier*')

            // submit form & wait reponse
            cy.closeAndVerifyModal(selectorModal, 'submitEditChauffeur', 'chauffeur_edit');

            cy.checkDataInDatatable(newDrivers[index], 'nom', 'tableChauffeur_id', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Nature', () => {
    beforeEach(() => {
        interceptRoute(routes.nature_api);
        interceptRoute(routes.nature_new);
        interceptRoute(routes.nature_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'nature_param_index');
    })

    it('should add a new nature', () => {

        const newNature = {
            label: 'OUTILS',
            code: 'OUTILS',
            quantity: '1',
        }
        const propertiesMap = {
            'Libellé': 'label',
            'Code': 'code',
            "Quantité par défaut de l'arrivage": 'quantity',
        }
        const selectorModal = '#modalNewNature';

        cy.openModal(selectorModal, 'label')

        cy.get(`${selectorModal}`).should('be.visible', {timeout: 8000}).then(() => {

            // edit valuesz
            const languageInput = "Français"
            cy.get(`#modalNewNature [data-cypress=${languageInput}]`).type(newNature.label);

            cy.typeInModalInputs(selectorModal, newNature, ['label']);

            // submit form & wait reponse
            cy.closeAndVerifyModal(selectorModal, 'submitNewNature', 'nature_new');
        })
        cy.wait('@nature_api');

        // check datatable after edit
        cy.checkDataInDatatable(newNature, 'label', 'tableNatures', propertiesMap);
    })

    it('should edit a nature', () => {

        const natureToEdit = ['OUTILS']
        const newNatures = [{
            label: 'COLIS',
            code: 'COLIS',
            quantity: '10',
        }]
        const propertiesMap = {
            'Libellé': 'label',
            'Code': 'code',
            "Quantité par défaut de l'arrivage": 'quantity',
        }
        const selectorModal = '#modalEditNature';

        cy.wait('@nature_api');

        natureToEdit.forEach((natureToEditName, index) => {
            cy.clickOnRowInDatatable('tableNatures', natureToEditName);

            cy.get(selectorModal).should('be.visible');

            // edit values
            const languageInput = "Français"
            cy.get(`#modalEditNature [data-cypress=${languageInput}]`).click().clear().type(newNatures[index].label);

            cy.typeInModalInputs(selectorModal, newNatures[index], ['label']);

            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitEditNature', 'nature_edit');
            cy.wait('@nature_api');

            cy.checkDataInDatatable(newNatures[index], 'label', 'tableNatures', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Véhicules', () => {
    beforeEach(() => {
        interceptRoute(routes.vehicle_edit);
        interceptRoute(routes.vehicule_api);
        interceptRoute(routes.vehicule_new);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'vehicle_index');
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

describe('Add and edit components in Referentiel > Projet', () => {
    beforeEach(() => {
        interceptRoute(routes.project_api);
        interceptRoute(routes.project_new);
        interceptRoute(routes.project_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'project_index');
    })

    it('should add a new project', () => {

        const newProject = {
            code: 'GAZO',
            projectManager: 'Admin',
        }
        const propertiesMap = {
            'Code': 'code',
            'Chef de projet': 'projectManager',
        }
        const selectorModal = '#modalNewProject';
        // open modal
        cy.openModal(selectorModal, 'code','[data-toggle="modal"]' );

        cy.get(selectorModal).should('be.visible', {timeout: 8000}).then(() => {

            // edit values (let .wait() to wait for input be selected i don't know why it doesn't work without it)
            cy.get(`${selectorModal} input[name=code]`).wait(500).type(newProject.code);

            cy.select2Ajax('projectManager', newProject.projectManager);

            // submit form
            cy.closeAndVerifyModal(selectorModal, undefined, 'project_new', true);
        })
        // check datatable is reloaded
        cy.wait('@project_api');

        // check datatable after edit
        cy.checkDataInDatatable(newProject, 'code', 'projectTable_id', propertiesMap);
    })

    it('should edit a project', () => {

        const projectToEdit = ['PROJET']
        const newProjects = [{
            code: 'RACLETTE',
            projectManager: 'Lambda',
        }]
        const propertiesMap = {
            'Code': 'code',
            'Chef de projet': 'projectManager',
        }
        const selectorModal = '#modalEditProject';
        cy.wait('@project_api');

        projectToEdit.forEach((projectToEditName, index) => {
            cy.clickOnRowInDatatable('projectTable_id', projectToEditName);

            cy.get(selectorModal).should('be.visible');

            // edit values
            cy.typeInModalInputs(selectorModal, newProjects[index], ['projectManager']);
            // remove previous value
            cy.clearSelect2AjaxValues('projectManager');
            // add new value
            cy.select2Ajax('projectManager', newProjects[index].projectManager, 'modalEditProject', false)

            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitEditProject', 'project_edit');
            cy.wait('@project_api');

            cy.checkDataInDatatable(newProjects[index], 'code', 'projectTable_id', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Clients', () => {
    beforeEach(() => {
        interceptRoute(routes.customer_api);
        interceptRoute(routes.customer_new);
        interceptRoute(routes.customer_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'customer_index');
    })

    it('should add a new customer', () => {

        const newCustomer = {
            name: 'Toto',
            address: 'Bègles',
            recipient: 'PAS',
            'phone-number': '0218923090',
            email: 'admin@wiilog.fr',
            fax: '0218923091',
        }
        const propertiesMap = {
            'Adresse': 'address',
            'Destinataire': 'recipient',
            'Téléphone': 'phone-number',
            'Email': 'email',
            'Fax': 'fax',
        }
        const selectorModal = '#modalNewCustomer';
        cy.openModal(selectorModal, 'name');

        cy.get(selectorModal).should('be.visible', {timeout: 8000}).then(() => {

            // edit values (wait for input be selected i don't know why it doesn't work without it)
            cy.get(`${selectorModal} input[name=name]`).wait(500).type(newCustomer.name);
            cy.get(`${selectorModal} textarea[name=address]`).type(newCustomer.address);
            cy.typeInModalInputs(selectorModal, newCustomer, ['address', 'name']);

            cy.closeAndVerifyModal(selectorModal, 'submitNewCustomer', 'customer_new', true);
        })
        // check datatable is reloaded
        cy.wait('@customer_api');

        cy.checkDataInDatatable(newCustomer, 'name', 'customerTable', propertiesMap, ['name']);
    })

    it('should edit a customer', () => {

        const customerToEdit = ['Client']
        let newCustomers = [{
            name: 'RE',
            address: 'Bordeaux',
            recipient: 'POND',
            'phone-number': '0218923092',
            email: 'tata@wiilog.fr',
            fax: '0218923093',
        }]
        const propertiesMap = {
            'Adresse': 'address',
            'Destinataire': 'recipient',
            'Téléphone': 'phone-number',
            'Email': 'email',
            'Fax': 'fax',
        }
        const selectorModal = '#modalEditCustomer';
        cy.wait('@customer_api');

        customerToEdit.forEach((customerToEditName, index) => {
            cy.clickOnRowInDatatable('customerTable', customerToEditName);
            cy.get(selectorModal).should('be.visible');

            // edit values
            cy.get(`${selectorModal} [name=name]`).clear().click().type(newCustomers[index].name);
            cy.get(`${selectorModal} [name=address]`).clear().click().type(newCustomers[index].address);
            cy.typeInModalInputs(selectorModal, newCustomers[index], ['address']);

            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitEditCustomer', 'customer_edit')

            cy.wait('@customer_api');

            cy.checkDataInDatatable(newCustomers[index], 'name', 'customerTable', propertiesMap, ['name'])
        })
    })
})
