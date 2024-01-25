const user = Cypress.config('user');

describe('Setup the environment', () => {
    it('Reset the db', () => {
        cy.startingCypressEnvironnement('$FTP_HOST')
    });
})


describe('Add and edit components in Referentiel > Fournisseur', () => {
    beforeEach(() => {
        cy.intercept('POST', 'fournisseur/api').as('supplier_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'supplier_index');
    })

    it('should add a new supplier', () => {

        const suplier = {
            name: 'RENAULT',
            code: 'RENAULT',
            possibleCustoms: 'oui',
            urgent: 'oui',
        }
        const propertiesMap = {
            'Nom': 'name',
            'Code fournisseur': 'code',
            'Possible douane': 'possibleCustoms',
            'Urgent': 'urgent',
        };

        cy.intercept('POST', 'fournisseur/creer').as('supplier_new');
        // ouvre la modal
        cy.get(`button[data-target='#modalNewFournisseur']`).click();

        cy.get('#modalNewFournisseur').should('be.visible', {timeout: 4000}).then(() => {
            cy.get('#modalNewFournisseur input[name=name]').should('be.visible').then(() => {

                cy.get('#modalNewFournisseur input[name=name]').type(suplier.name);
                cy.get('#modalNewFournisseur input[name=code]').type(suplier.code);

                const possibleCustomsInput = cy.get('#modalNewFournisseur input[name=possibleCustoms]');
                suplier.possibleCustoms === 'oui'
                    ? possibleCustomsInput.check().should('be.checked')
                    : possibleCustomsInput.uncheck().should('not.be.checked');

                const urgentInput = cy.get('#modalNewFournisseur input[name=urgent]');
                suplier.urgent === 'oui'
                    ? urgentInput.check().should('be.checked')
                    : urgentInput.uncheck().should('not.be.checked');

                // soumet le formulaire et verifie que les requetes sont bien passées (pas de 500)
                cy.get('button#submitNewFournisseur').click().wait('@supplier_new').then(
                    (xhr) => {
                        expect(xhr.response.statusCode).to.not.equal(500);
                    }
                )
                cy.get('#modalNewFournisseur').should('not.be.visible');
            })
        })

        // test du datatable
        // verifie que les requetes sont bien passées (pas de 500)
        cy.wait('@supplier_api').then(
            (xhr) => {
                expect(xhr.response.statusCode).to.not.equal(500);
            }
        );

        cy.checkDataInDatatable(suplier, 'name', 'supplierTable_id', propertiesMap);
    })

    it('should edit a supplier', () => {

        const supliersToEdit = ['FOURNISSEUR', 'GREGZAAR']

        const newSupliers = [{
            name: 'IKEA',
            code: 'IKEA',
            possibleCustoms: 'oui',
            urgent: 'oui',
        },
            {
                name: 'GREGZaaER',
                code: 'GREGZER',
                possibleCustoms: 'non',
                urgent: 'non',
            }]

        const propertiesMap = {
            'Nom': 'name',
            'Code fournisseur': 'code',
            'Possible douane': 'possibleCustoms',
            'Urgent': 'urgent',
        };

        if (newSupliers.length !== supliersToEdit.length) {
            throw new Error('The number of suppliers to edit and the number of new suppliers are different')
        }

        cy.intercept('POST', 'fournisseur/modifier').as('supplier_edit');
        cy.wait('@supplier_api');

        supliersToEdit.forEach((suplierToEditName, index) => {
            // on trouve le fournisseur à éditer et on clique dessus
            cy.clickOnRowInDatatable('supplierTable_id', suplierToEditName);
            cy.get('#modalEditFournisseur').should('be.visible');

            cy.get('#modalEditFournisseur input[name=name]').click().clear().type(newSupliers[index].name);
            cy.get('#modalEditFournisseur input[name=code]').click().clear().type(newSupliers[index].code);

            const possibleCustomsInput = cy.get('#modalEditFournisseur input[name=possibleCustoms]');
            newSupliers[index].possibleCustoms === 'oui'
                ? possibleCustomsInput.check().should('be.checked')
                : possibleCustomsInput.uncheck().should('not.be.checked');

            const urgentInput = cy.get('#modalEditFournisseur input[name=urgent]');
            newSupliers[index].urgent === 'oui'
                ? urgentInput.check().should('be.checked')
                : urgentInput.uncheck().should('not.be.checked');

            cy.get('button#submitEditFournisseur').click().wait('@supplier_edit').then(
                (xhr) => {
                    expect(xhr.response.statusCode).to.not.equal(500);
                });
            cy.get('#modalEditFournisseur').should('not.be.visible');

            // on attend que le tableau soit rechargé
            cy.wait('@supplier_api');

            // check all data in datatable are correct after edit
            cy.checkDataInDatatable(newSupliers[index], 'name', 'supplierTable_id', propertiesMap);
        });
    })
})

describe('Add and edit components in Referentiel > Emplacements', () => {
    beforeEach(() => {
        cy.intercept('POST', 'emplacement/api').as('emplacement_api');
        cy.intercept('POST', 'emplacements/groupes/api').as('emplacements_groupes_api');
        cy.intercept('POST', 'zones/api').as('zones_api');

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'emplacement_index');
    })

    it('should add a new location', () => {
        cy.intercept('POST', 'emplacement/creer').as('emplacement_new');
        cy.intercept('GET', 'emplacement/api-new').as('location_api_new');
        cy.get(`button[data-cy-name="new-location-button"]`).should('be.visible')
            .click()
            .wait('@location_api_new');

        const newLocation = {
            label: 'STOCK',
            description: 'Non défini',
            zone: 'Activité Standard',
        }

        cy.get('#modalNewEmplacement').should('be.visible', {timeout: 4000}).then(() => {
            cy.get('#modalNewEmplacement input[name=label]').type(newLocation.label);
            cy.get('#modalNewEmplacement input[name=description]').type(newLocation.description);
            cy.select2Ajax('zone', newLocation.zone)

            cy.get('button#submitNewEmplacement').click().wait(['@emplacement_new', '@emplacement_api']).then((xhr) => {
                expect(xhr[0].response.statusCode).to.not.equal(500);
                expect(xhr[1].response.statusCode).to.not.equal(500);
            });
        })

        // TODO VOIR AVEC CP SI ON FAIT TOUT LES CHAMPS DU FORMULAIRE

        cy.get('#modalNewEmplacement').should('not.be.visible');
        cy.wait('@emplacement_api');

        const propertiesMap = {
            'Nom': 'label',
            'Description': 'description',
            'Zone': 'zone',
        }

        cy.checkDataInDatatable(newLocation, 'label', 'locationsTable', propertiesMap);

    })

    it('should edit a location', () => {
        cy.intercept('POST', 'emplacement/edit').as('emplacement_edit');
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

        locationToEdit.forEach((locationToEditName, index) => {
            // click on the location to edit
            cy.clickOnRowInDatatable('locationsTable', locationToEditName);
            cy.get('#modalEditEmplacement').should('be.visible');

            // edit values
            cy.get('#modalEditEmplacement input[name=label]').click().clear().type(newLocations[index].label);
            cy.get('#modalEditEmplacement input[name=description]').click().clear().type(newLocations[index].description);
            cy.select2Ajax('zone', newLocations[index].zone)

            // submit the form
            cy.get('button#submitEditEmplacement').click().wait('@emplacement_edit');
            cy.get('#modalEditEmplacement').should('not.be.visible');
            cy.wait('@emplacement_api');

            // check all data in datatable are correct after edit
            cy.checkDataInDatatable(newLocations[index], 'label', 'locationsTable', propertiesMap);
        })
    })

    it('should add a new group', () => {
        cy.intercept('POST', 'emplacements/groupes/creer').as('location_group_new');

        let newLocationGroup = {
            label: 'TOTO',
            description: 'Non défini',
            status: 'Actif',
            locations: ['ZONE 007'],
        }

        const propertiesMap = {
            'Nom': 'label',
            'Description': 'description',
            'Actif / Inactif': 'status',
            'Nombre emplacements': 'locations',
        }

        // press the "Groupes" menu
        const menu = 'Groupes'
        cy.get('.nav-item').find('a').contains(menu).click();

        cy.get(`button[data-toggle='modal']`).filter(':visible').click();

        cy.get('#modalNewLocationGroup').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewLocationGroup input[name=label]').should('be.visible').then(() => {

                // edit values
                cy.get('#modalNewLocationGroup input[name=label]').type(newLocationGroup.label);
                cy.get('#modalNewLocationGroup [name=description]').type(newLocationGroup.description);

                // Toggle button status
                const statusValue = newLocationGroup.status.trim().toLowerCase() === 'actif' ? "1" : "0";
                cy.get(`#modalNewLocationGroup [data-title='Statut'] input[value="${statusValue}"]`).click();

                cy.log(newLocationGroup.locations);

                // refactor this part with the object newLocationGroup -> it doesn't work with newLocationGroup.locations :(
                cy.select2AjaxMultiple('locations', ['ZONE 007']);

                cy.get('button#submitNewLocationGroup').click().wait(['@location_group_new', '@emplacements_groupes_api']);
            })
        })

        cy.get('#modalNewLocationGroup').should('not.be.visible');
        cy.wait('@emplacements_groupes_api');

        // change the number of locations to match with the datatable value
        newLocationGroup = {...newLocationGroup, locations: newLocationGroup.locations.length}
        cy.checkDataInDatatable(newLocationGroup, 'label', 'groupsTable', propertiesMap)

    })

    it('should edit a group', () => {
        cy.intercept('POST', 'emplacements/groupes/modifier').as('location_group_edit');

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

        // press the "Groupes" menu
        const menu = 'Groupes'
        cy.get('.nav-item').find('a').contains(menu).click();

        // wait for the datatable to be loaded before clicking on the row
        cy.wait('@emplacements_groupes_api');

        locationGroupToEdit.forEach((locationGroupToEditName, index) => {
            cy.clickOnRowInDatatable('groupsTable', locationGroupToEditName);
            cy.get('#modalEditLocationGroup').should('be.visible');

            // edit values
            cy.get('#modalEditLocationGroup input[name=label]').click().clear().type(newLocationGroups[index].label);
            cy.get('#modalEditLocationGroup [name=description]').click().clear().type(newLocationGroups[index].description);

            // Edit toggle button status
            const statusValue = newLocationGroups[index].status.trim().toLowerCase() === 'actif' ? "1" : "0";
            cy.get(`#modalEditLocationGroup [data-title='Statut'] input[value="${statusValue}"]`).click();

            // remove previous select2 values
            cy.get('select[name=locations]').as('locationsSelect');
            // todo refactor if you put more than 1 location
            cy.get('@locationsSelect')
                .siblings('.select2')
                .find('.select2-selection__choice__remove')
                .click();

            // add new select2 values
            cy.select2AjaxMultiple('locations', newLocationGroups[index].locations, 'modalEditLocationGroup', false);

            // submit form
            cy.get('button#submitEditLocationGroup').click().wait('@location_group_edit');
            cy.get('#modalEditLocationGroup').should('not.be.visible');

            // reload datatable
            cy.wait('@emplacements_groupes_api');

            // check datatable after edit
            newLocationGroups[index] = {...newLocationGroups[index], locations: newLocationGroups[index].locations.length}
            cy.checkDataInDatatable(newLocationGroups[index], 'label', 'groupsTable', propertiesMap)
        })
    })

    it('should add a new area', () => {
        cy.intercept('POST', 'zones/creer').as('zone_new');

        const newArea = {
            name: 'ZONE 007',
            description: 'Non défini',
            active: 'Non',
        }
        const propertiesMap = {
            'Nom': 'name',
            'Description': 'description',
            'Actif': 'active',
        }

        // press the "Zones" menu
        const menu = 'Zones'
        cy.get('.nav-item').find('a').contains(menu).click();

        cy.get(`button[data-toggle='modal']`).filter(':visible').click();
        cy.get('#modalNewZone').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewZone input[name=name]').should('be.visible').then(() => {

                cy.get('#modalNewZone input[name=name]').type(newArea.name)
                cy.get('#modalNewZone [name=description]').type(newArea.description);

                // Edit toggle button status
                const statusValue = newArea.active.trim().toLowerCase() === 'oui' ? "1" : "0";
                cy.get(`#modalNewZone [data-title='Statut*'] input[value="${statusValue}"]`).click({force: true});

                cy.get(`#modalNewZone button[type='submit']`).click().wait(['@zone_new', '@zones_api']);
            })
        })

        cy.get('#modalNewZone').should('not.be.visible');
        cy.wait('@zones_api');

        cy.checkDataInDatatable(newArea, 'name', 'zonesTable', propertiesMap)
    })

    it('should edit an area', () => {
        cy.intercept('POST', 'zones/modifier').as('zone_edit');

        const areaToEdit = ['ZONE']
        const newAreas = [{
            name: 'ZONE NORD',
            description: 'Non défini',
            active: 'Oui',
        }]
        const propertiesMap = {
            'Nom': 'name',
            'Description': 'description',
            'Actif': 'active',
        }

        if (newAreas.length !== areaToEdit.length) {
            throw new Error('The number of areas to edit and the number of new areas are different')
        }

        // press the "Zones" menu
        const menu = 'Zones'
        cy.get('.nav-item').find('a').contains(menu).click();

        // load datatable
        cy.wait('@zones_api');

        areaToEdit.forEach((areaToEditName, index) => {
            // click on the row to edit
            cy.clickOnRowInDatatable('zonesTable', areaToEditName);
            cy.get('#modalEditZone').should('be.visible');

            // edit values
            cy.get('#modalEditZone input[name=name]').click().clear().type(newAreas[index].name);
            cy.get('#modalEditZone [name=description]').click().clear().type(newAreas[index].description);

            // Edit toggle button status
            const statusValue = newAreas[index].active.trim().toLowerCase() === 'oui' ? "1" : "0";
            cy.get(`#modalEditZone [data-title='Statut*'] input[value="${statusValue}"]`).click({force: true});

            // submit form
            cy.get('button#submitEditZone').click().wait('@zone_edit');
            cy.get('#modalEditZone').should('not.be.visible');

            // reload datatable and check after edit
            cy.wait('@zones_api');

            cy.checkDataInDatatable(newAreas[index], 'name', 'zonesTable', propertiesMap)
        })

    })
})

describe('Add and edit components in Referentiel > Transporteurs', () => {
    beforeEach(() => {
        cy.intercept('POST', 'transporteur/api').as('transporteur_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'transporteur_index');
    })

    it('should add a new transporter', () => {
        cy.intercept('POST', 'transporteur/save').as('transporteur_save');

        const transporter = {
            label: 'MY LONG TRANSPORTEUR IS INSANE',
            code: 'WIILOG',
        }
        const propertiesMap = {
            'Nom': 'label',
            'Code': 'code',
        }

        // open modal
        cy.get(`[data-cypress="newCarrier"]`).click()

        cy.get('#modalTransporteur').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalTransporteur input[name=label]').should('be.visible').then(() => {

                // edit values
                cy.get('#modalTransporteur input[name=label]').type(transporter.label);
                cy.get('#modalTransporteur input[name=code]').type(transporter.code);

                // submit form
                cy.get(`button[type='submit']`).click().wait(['@transporteur_save', '@transporteur_api']);
            })
        })
        cy.get('#modalTransporteur').should('not.be.visible');
        // reload datatable
        cy.wait('@transporteur_api');
        // check datatable after edit
        cy.checkDataInDatatable(transporter, 'label', 'tableTransporteur_id', propertiesMap);
    })

    it('should edit a transporter', () => {
        cy.intercept('POST', 'transporteur/save?*').as('transporteur_save_edit');

        const transporterToEdit = ['TRANSPORTEUR']
        const newTransporters = [{
            label: 'LA POSTE',
            code: 'LA POSTE',
        }]
        const propertiesMap = {
            'Nom': 'label',
            'Code': 'code',
        }

        // load datatable
        cy.wait('@transporteur_api');

        transporterToEdit.forEach((transporterToEditName, index) => {
            // click on the row to edit
            cy.clickOnRowInDatatable('tableTransporteur_id', transporterToEditName);

            cy.get('#modalTransporteur').should('be.visible');

            // edit values
            cy.get('#modalTransporteur input[name=label]').click().clear().type(newTransporters[index].label);
            cy.get('#modalTransporteur input[name=code]').click().clear().type(newTransporters[index].code);

            // submit form
            cy.get(`button[type='submit']`).click().wait('@transporteur_save_edit');
            cy.get('#modalTransporteur').should('not.be.visible');

            // reload datatable
            cy.wait('@transporteur_api');

            cy.checkDataInDatatable(newTransporters[index], 'label', 'tableTransporteur_id', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Chauffeurs', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'chauffeur_index');
    })

    it('should add a new driver', () => {
        cy.intercept('POST', 'chauffeur/creer').as('chauffeur_new');

        const driver = {
            name: 'Eve',
            firstname: 'Adam',
            documentID: '12345632',
            carrier: 'MY LONG TRANSPORTEUR IS INSANE',
        }
        const propertiesMap = {
            'Nom': 'name',
            'Prenom': 'firstname',
            'DocumentID': 'documentID',
            'Transporteur': 'carrier',
        }

        cy.get(`a[data-target='#modalNewChauffeur']`).click();

        cy.get('#modalNewChauffeur').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewChauffeur input[name=nom]').should('be.visible').then(() => {

                // edit values
                cy.get('#modalNewChauffeur input[name=nom]').type(driver.name);
                cy.get('#modalNewChauffeur input[name=prenom]').type(driver.firstname);
                cy.get('#modalNewChauffeur input[name=documentID]').type(driver.documentID);

                // todo bug
                cy.select2Ajax('transporteur', driver.carrier , '', true, '/chauffeur/autocomplete*')

                cy.get('button#submitNewChauffeur').click().wait('@chauffeur_new');
            })
        })
        cy.get('#modalNewChauffeur').should('not.be.visible');

        cy.checkDataInDatatable(driver, 'name', 'tableChauffeur_id', propertiesMap);
    })


    it('should edit a driver', () => {
        cy.intercept('POST', 'chauffeur/modifier').as('chauffeur_edit');

        const driverToEdit = ['Chauffeur']
        const newDrivers = [{
            name: 'Robinet',
            firstname: 'Pluviote',
            documentID: '666',
            carrier: 'MY LONG TRANSPORTEUR IS INSANE',
        }]
        const propertiesMap = {
            'Nom': 'name',
            'Prenom': 'firstname',
            'DocumentID': 'documentID',
            'Transporteur': 'carrier',
        }

        driverToEdit.forEach((driverToEditName, index) => {
            cy.clickOnRowInDatatable('tableChauffeur_id', driverToEditName);

            cy.get('#modalEditChauffeur').should('be.visible');

            // edit values
            cy.get('#modalEditChauffeur input[name=nom]').click().clear().type(newDrivers[index].name);
            cy.get('#modalEditChauffeur input[name=prenom]').click().clear().type(newDrivers[index].firstname);
            cy.get('#modalEditChauffeur input[name=documentID]').click().clear().type(newDrivers[index].documentID);

            // clear previous value
            cy.get('select[name=transporteur]')
                .siblings('.select2')
                .find('.select2-selection__clear')
                .click();

            // todo bug
            cy.select2Ajax('transporteur', newDrivers[index].carrier, 'modalEditChauffeur', false, '/chauffeur/autocomplete*')

            cy.get('button#submitEditChauffeur').click().wait('@chauffeur_edit');
            cy.get('#modalEditChauffeur').should('not.be.visible');

            cy.checkDataInDatatable(newDrivers[index], 'name', 'tableChauffeur_id', propertiesMap)
        })
    })
})


describe('Add and edit components in Referentiel > Nature', () => {
    beforeEach(() => {
        cy.intercept('POST', 'nature-unite-logistique/api').as('nature_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'nature_param_index');
    })

    it('should add a new nature', () => {
        cy.intercept('POST', 'nature-unite-logistique/creer').as('nature_new');

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

        cy.get(`button[data-target='#modalNewNature']`).click();

        cy.get('#modalNewNature').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewNature input[name=label]').should('be.visible').then(() => {

                // edit values
                const languageInput = "Français"
                cy.get(`#modalNewNature [data-cypress=${languageInput}]`).type(newNature.label);
                cy.get('#modalNewNature input[name=code]').type(newNature.code);
                cy.get('#modalNewNature input[name=quantity]').type(newNature.quantity);

                // submit form & wait reponse
                cy.get(`button#submitNewNature`).click().wait(['@nature_new', '@nature_api']);
            })
        })
        // check modal is closed
        cy.get('#modalNewNature').should('not.be.visible');
        cy.wait('@nature_api');
        // check datatable after edit
        cy.checkDataInDatatable(newNature, 'label', 'tableNatures', propertiesMap);
    })

    it('should edit a nature', () => {
        cy.intercept('POST', 'nature-unite-logistique/modifier').as('nature_edit');

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

        cy.wait('@nature_api');

        natureToEdit.forEach((natureToEditName, index) => {
            cy.clickOnRowInDatatable('tableNatures', natureToEditName);

            cy.get('#modalEditNature').should('be.visible');

            // edit values
            const languageInput = "Français"
            cy.get(`#modalEditNature [data-cypress=${languageInput}]`).click().clear().type(newNatures[index].label);
            cy.get('#modalEditNature input[name=code]').click().clear().type(newNatures[index].code);
            cy.get('#modalEditNature input[name=quantity]').click().clear().type(newNatures[index].quantity);

            // submit form
            cy.get(`button#submitEditNature`).click().wait('@nature_edit');
            cy.get('#modalEditNature').should('not.be.visible');
            cy.wait('@nature_api');

            cy.checkDataInDatatable(newNatures[index], 'label', 'tableNatures', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Véhicules', () => {
    beforeEach(() => {
        cy.intercept('POST', 'vehicule/api').as('vehicule_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'vehicle_index');
    })

    it('should add a new vehicle', () => {
        cy.intercept('POST', 'vehicule/new').as('vehicule_new');

        const newVehicle = {
            registrationNumber: 'CL-010-RA',
        }
        const propertiesMap = {
            'Immatriculation': 'registrationNumber',
        }

        cy.get(`a[data-target='#modalNewVehicle']`).click();

        cy.get('#modalNewVehicle').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewVehicle input[name=registrationNumber]').should('be.visible').then(() => {

                // edit values
                cy.get('#modalNewVehicle input[name=registrationNumber]').type(newVehicle.registrationNumber);
                // submit form
                cy.get(`.modal-footer button.submit`).click().wait(['@vehicule_new', '@vehicule_api']);
            })
        })

        cy.get('#modalNewVehicle').should('not.be.visible');
        cy.wait('@vehicule_api');

        cy.checkDataInDatatable(newVehicle, 'registrationNumber', 'vehicleTable_id', propertiesMap);
    })

    it('should edit a vehicle', () => {
        cy.intercept('POST', 'vehicule/edit').as('vehicle_edit');
        cy.wait('@vehicule_api');

        const vehicleToEdit = ['VEHICULE']
        const newVehicles = [{
            registrationNumber: 'AA-000-AA',
        }]
        const propertiesMap = {
            'Immatriculation': 'registrationNumber',
        }

        vehicleToEdit.forEach((vehicleToEditName, index) => {
            cy.clickOnRowInDatatable('vehicleTable_id', vehicleToEditName);

            cy.get('#modalEditVehicle').should('be.visible');

            // edit values
            cy.get('#modalEditVehicle input[name=registrationNumber]').click().clear().type(newVehicles[index].registrationNumber);

            // submit form
            cy.get(`button#submitEditVehicle`).click().wait('@vehicle_edit');
            cy.get('#modalEditVehicle').should('not.be.visible');
            cy.wait('@vehicule_api');

            cy.checkDataInDatatable(newVehicles[index], 'registrationNumber', 'vehicleTable_id', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Projet', () => {
    beforeEach(() => {
        cy.intercept('POST', 'project/api').as('project_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'project_index');
    })

    it('should add a new project', () => {
        cy.intercept('POST', 'project/new').as('project_new');

        const newProject = {
            code: 'GAZO',
            projectManager: 'Admin',
        }
        const propertiesMap = {
            'Code': 'code',
            'Chef de projet': 'projectManager',
        }
        // open modal
        cy.get(`button[data-toggle='modal']`).click();

        cy.get('#modalNewProject').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewProject input[name=code]').should('be.visible').then(() => {

                // edit values (let .wait() to wait for input be selected i don't know why it doesn't work without it)
                cy.get('#modalNewProject input[name=code]').wait(500).type(newProject.code);
                cy.select2Ajax('projectManager', newProject.projectManager);

                // submit form
                cy.get(`#modalNewProject button[type=submit]`).click().wait(['@project_new', '@project_api']);
            })
        })
        // check modal is closed and datatable is reloaded
        cy.get('#modalNewProject').should('not.be.visible');
        cy.wait('@project_api');

        // check datatable after edit
        cy.checkDataInDatatable(newProject, 'code', 'projectTable_id', propertiesMap);
    })

    it('should edit a project', () => {
        cy.intercept('POST', 'project/edit').as('project_edit');
        cy.wait('@project_api');

        const projectToEdit = ['PROJET']
        const newProjects = [{
            code: 'RACLETTE',
            projectManager: 'Lambda',
        }]
        const propertiesMap = {
            'Code': 'code',
            'Chef de projet': 'projectManager',
        }

        projectToEdit.forEach((projectToEditName, index) => {
            cy.clickOnRowInDatatable('projectTable_id', projectToEditName);

            cy.get('#modalEditProject').should('be.visible');

            // edit values
            cy.get('#modalEditProject input[name=code]').click().clear().type(newProjects[index].code);
            // remove old value
            cy.get('select[name=projectManager]')
                .siblings('.select2')
                .find('.select2-selection__clear')
                .click();
            // add new value
            cy.select2Ajax('projectManager', newProjects[index].projectManager, 'modalEditProject', false)

            // submit form
            cy.get(`button#submitEditProject`).click().wait('@project_edit');
            cy.get('#modalEditProject').should('not.be.visible');
            cy.wait('@project_api');

            cy.checkDataInDatatable(newProjects[index], 'code', 'projectTable_id', propertiesMap)
        })
    })
})

describe('Add and edit components in Referentiel > Clients', () => {
    beforeEach(() => {
        cy.intercept('POST', 'clients/api').as('customer_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'customer_index');
    })

    it('should add a new customer', () => {
        cy.intercept('POST', 'clients/new').as('customer_new');
        cy.get(`button[data-toggle='modal']`).click();

        const newCustomer = {
            name: 'Toto',
            address: 'Bègles',
            recipient: 'PAS',
            phoneNumber: '0218923090',
            email: 'admin@wiilog.fr',
            fax: '0218923091',
        }
        const propertiesMap = {
            'Adresse': 'address',
            'Destinataire': 'recipient',
            'Téléphone': 'phoneNumber',
            'Email': 'email',
            'Fax': 'fax',
        }

        cy.get('#modalNewCustomer').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewCustomer input[name=name]').should('be.visible').then(() => {

                // edit values (wait for input be selected i don't know why it doesn't work without it)
                cy.get('#modalNewCustomer input[name=name]').wait(500).type(newCustomer.name);
                cy.get('#modalNewCustomer textarea[name=address]').type(newCustomer.address);
                cy.get('#modalNewCustomer input[name=recipient]').type(newCustomer.recipient);
                cy.get('#modalNewCustomer input[name=phone-number]').type(newCustomer.phoneNumber);
                cy.get('#modalNewCustomer input[name=email]').type(newCustomer.email);
                cy.get('#modalNewCustomer input[name=fax]').type(newCustomer.fax);

                cy.get(`#modalNewCustomer button[type=submit]`).click().wait(['@customer_new', '@customer_api']);
            })
        })
        // check modal is closed and datatable is reloaded
        cy.get('#modalNewCustomer').should('not.be.visible');
        cy.wait('@customer_api');

        cy.checkDataInDatatable(newCustomer, 'name', 'customerTable', propertiesMap, ['name']);
    })

    it('should edit a customer', () => {
        cy.intercept('POST', 'clients/edit').as('customer_edit');
        cy.wait('@customer_api');

        const customerToEdit = ['Client']
        let newCustomers = [{
            name: 'RE',
            address: 'Bordeaux',
            recipient: 'POND',
            phoneNumber: '0218923092',
            email: 'tata@wiilog.fr',
            fax: '0218923093',
        }]
        const propertiesMap = {
            'Adresse': 'address',
            'Destinataire': 'recipient',
            'Téléphone': 'phoneNumber',
            'Email': 'email',
            'Fax': 'fax',
        }

        customerToEdit.forEach((customerToEditName, index) => {
            cy.clickOnRowInDatatable('customerTable', customerToEditName);
            cy.get('#modalEditCustomer').should('be.visible');

            // edit values
            cy.get('#modalEditCustomer input[name=name]').clear().click().type(newCustomers[index].name);
            cy.get('#modalEditCustomer [name=address]').clear().click().type(newCustomers[index].address);
            cy.get('#modalEditCustomer [name=recipient]').clear().click().type(newCustomers[index].recipient);
            cy.get('#modalEditCustomer [name=phone-number]').clear().click().type(newCustomers[index].phoneNumber);
            cy.get('#modalEditCustomer [name=email]').clear().click().type(newCustomers[index].email);
            cy.get('#modalEditCustomer [name=fax]').clear().click().type(newCustomers[index].fax);

            // submit form
            cy.get(`button#submitEditCustomer`).click().wait('@customer_edit');
            cy.get('#modalEditCustomer').should('not.be.visible');
            cy.wait('@customer_api');

            cy.checkDataInDatatable(newCustomers[index], 'name', 'customerTable', propertiesMap, ['name'])
        })
    })
})
