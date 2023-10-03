const user = Cypress.config('user');
describe('Add and edit components in Referentiel > Fournisseur', () => {
    before(() => {
        cy.resetDatabase('BDD_scratch.cypress.sql')
    })
    beforeEach(() => {
        cy.intercept('POST', 'fournisseur/api').as('supplier_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'supplier_index');
    })

    it('should add a new supplier', () => {
        cy.intercept('POST', 'fournisseur/creer').as('supplier_new');
        cy.get(`button[data-target='#modalNewFournisseur']`).click();
        cy.get('#modalNewFournisseur').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewFournisseur input[name=name]').should('be.visible').then(() => {
                cy.get('#modalNewFournisseur input[name=name]').type('RENAULT');
                cy.get('#modalNewFournisseur input[name=code]').type('RENAULT');
                cy.get('#modalNewFournisseur input[name=possibleCustoms]').check();
                cy.get('#modalNewFournisseur input[name=urgent]').check();
                cy.get('button#submitNewFournisseur').click().wait(['@supplier_new', '@supplier_api']);
            })
        })
        cy.get('#modalNewFournisseur').should('not.be.visible');
        cy.wait('@supplier_api');
        cy.get('#supplierTable_id tbody td').contains('RENAULT').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('RENAULT');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('RENAULT');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('oui');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('oui');
        });
    })

    it('should edit a supplier', () => {
        cy.intercept('POST', 'fournisseur/modifier').as('supplier_edit');
        cy.wait('@supplier_api');
        cy.get('#supplierTable_id tbody td').contains('FOURNISSEUR').click();
        cy.get('#modalEditFournisseur').should('be.visible');
        cy.get('#modalEditFournisseur input[name=name]').click().clear().type('IKEA');
        cy.get('#modalEditFournisseur input[name=code]').click().clear().type('IKEA');
        cy.get('#modalEditFournisseur input[name=possibleCustoms]').check();
        cy.get('#modalEditFournisseur input[name=urgent]').check();
        cy.get('button#submitEditFournisseur').click().wait('@supplier_edit');
        cy.get('#modalEditFournisseur').should('not.be.visible');
        cy.wait('@supplier_api');
        cy.get('#supplierTable_id tbody td').contains('IKEA').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('IKEA');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('IKEA');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('oui');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('oui');
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
        cy.get(`button[data-toggle='modal']`).filter(':visible').click().wait('@location_api_new');
        cy.get('#modalNewEmplacement').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewEmplacement input[name=label]').should('be.visible').then(() => {
                cy.get('#modalNewEmplacement input[name=label]').type('STOCK');
                cy.get('#modalNewEmplacement input[name=description]').type('Non défini');
                cy.select2Ajax('zone', 'Activité Standard')
                cy.get('button#submitNewEmplacement').click().wait(['@emplacement_new', '@emplacement_api']);
            })
        })
        cy.get('#modalNewEmplacement').should('not.be.visible');
        cy.wait('@emplacement_api');
        cy.get('#locationsTable tbody td').contains('STOCK').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('STOCK');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non défini');
        });
    })

    it('should edit a location', () => {
        cy.intercept('POST', 'emplacement/edit').as('emplacement_edit');
        cy.wait('@emplacement_api');
        cy.get('#locationsTable tbody td').contains('EMPLACEMENT').click();
        cy.get('#modalEditEmplacement').should('be.visible');
        cy.get('#modalEditEmplacement input[name=label]').click().clear().type('ACCUEIL');
        cy.get('#modalEditEmplacement input[name=description]').click().clear().type('Cypress');
        cy.get('button#submitEditEmplacement').click().wait('@emplacement_edit');
        cy.get('#modalEditEmplacement').should('not.be.visible');
        cy.wait('@emplacement_api');
        cy.get('#locationsTable tbody td').contains('ACCUEIL').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('ACCUEIL');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Cypress');
        });
    })

    it('should add a new group', () => {
        cy.intercept('POST', 'emplacements/groupes/creer').as('location_group_new');
        cy.get('.nav-item').eq(1).click();
        cy.get(`button[data-toggle='modal']`).filter(':visible').click();
        cy.get('#modalNewLocationGroup').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewLocationGroup input[name=label]').should('be.visible').then(() => {
                cy.get('#modalNewLocationGroup input[name=label]').type('SITE A');
                cy.get('#modalNewLocationGroup [name=description]').type('Non défini');
                cy.get(`#modalNewLocationGroup [data-title='Statut'] input`).eq(0).click();
                cy.select2AjaxMultiple('locations', ['LABO 11', 'ZONE 41']);
                cy.get('button#submitNewLocationGroup').click().wait(['@location_group_new', '@emplacements_groupes_api']);
            })
        })
        cy.get('#modalNewLocationGroup').should('not.be.visible');
        cy.wait('@emplacements_groupes_api');
        cy.get('#groupsTable tbody td').contains('SITE A').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('SITE A');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non défini');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('Actif');
            cy.wrap(td).parent('tr').find('td').eq(5).contains('2');
        });
    })

    it('should edit a group', () => {
        cy.intercept('POST', 'emplacements/groupes/modifier').as('location_group_edit');
        cy.get('.nav-item').eq(1).click();
        cy.wait('@emplacements_groupes_api');
        cy.get('#groupsTable tbody td').contains('GROUPE').click();
        cy.get('#modalEditLocationGroup').should('be.visible');
        cy.get('#modalEditLocationGroup input[name=label]').click().clear().type('TERRAIN A');
        cy.get('#modalEditLocationGroup [name=description]').click().clear().type('Cypress');
        cy.get(`#modalEditLocationGroup [data-title='Statut'] input`).eq(1).click();
        cy.get('select[name=locations]')
            .siblings('.select2')
            .find('.select2-selection__choice__remove')
            .click();
        cy.select2AjaxMultiple('locations', ['LABO 11', 'ZONE 41'], 'modalEditLocationGroup', false);
        cy.get('button#submitEditLocationGroup').click().wait('@location_group_edit');
        cy.get('#modalEditLocationGroup').should('not.be.visible');
        cy.wait('@emplacements_groupes_api');
        cy.get('#groupsTable tbody td').contains('TERRAIN A').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('TERRAIN A');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Cypress');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('Inactif');
            cy.wrap(td).parent('tr').find('td').eq(5).contains('2');
        });
    })

    it('should add a new area', () => {
        cy.intercept('POST', 'zones/creer').as('zone_new');
        cy.get('.nav-item').eq(2).click();
        cy.get(`button[data-toggle='modal']`).filter(':visible').click();
        cy.get('#modalNewZone').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewZone input[name=name]').should('be.visible').then(() => {
                cy.get('#modalNewZone input[name=name]').type('COMMODE');
                cy.get('#modalNewZone [name=description]').type('Non défini');
                cy.get(`#modalNewZone [data-title='Statut*'] input`).eq(1).click({force: true});
                cy.get(`#modalNewZone button[type='submit']`).click().wait(['@zone_new', '@zones_api']);
            })
        })
        cy.get('#modalNewZone').should('not.be.visible');
        cy.wait('@zones_api');
        cy.get('#zonesTable tbody td').contains('COMMODE').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('COMMODE');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Non défini');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non');
        });
    })

    it('should edit an area', () => {
        cy.intercept('POST', 'zones/modifier').as('zone_edit');
        cy.get('.nav-item').eq(2).click();
        cy.wait('@zones_api');
        cy.get('#zonesTable tbody td').contains('ZONE').click();
        cy.get('#modalEditZone').should('be.visible');
        cy.get('#modalEditZone input[name=name]').click().clear().type('BAT 123');
        cy.get('#modalEditZone [name=description]').click().clear().type('Cypress');
        cy.get(`#modalEditZone [data-title='Statut*'] input`).eq(1).click({force: true});
        cy.get('button#submitEditZone').click().wait('@zone_edit');
        cy.get('#modalEditZone').should('not.be.visible');
        cy.wait('@zones_api');
        cy.get('#zonesTable tbody td').contains('BAT 123').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('BAT 123');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Cypress');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non');
        });
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
        cy.get(`a[data-target='#modalNewChauffeur']`).click();
        cy.get('#modalNewChauffeur').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewChauffeur input[name=nom]').should('be.visible').then(() => {
                cy.get('#modalNewChauffeur input[name=nom]').type('Troijours');
                cy.get('#modalNewChauffeur input[name=prenom]').type(' Adam');
                cy.get('#modalNewChauffeur input[name=documentID]').type('2');
                cy.select2Ajax('transporteur', 'DHL', '', true, '/chauffeur/autocomplete*')
                cy.get('button#submitNewChauffeur').click().wait('@chauffeur_new');
            })
        })
        cy.get('#modalNewChauffeur').should('not.be.visible');
        cy.get('#tableChauffeur_id tbody td').contains('Troijours').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('Troijours');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Adam');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('2');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('DHL');
        });
    })

    it('should edit a driver', () => {
        cy.intercept('POST', 'chauffeur/modifier').as('chauffeur_edit');
        cy.get('#tableChauffeur_id tbody td').contains('Chauffeur').click();
        cy.get('#modalEditChauffeur').should('be.visible');
        cy.get('#modalEditChauffeur input[name=nom]').click().clear().type('Georgines');
        cy.get('#modalEditChauffeur input[name=prenom]').click().clear().type('Marceline');
        cy.get('#modalEditChauffeur input[name=documentID]').click().clear().type('10');
        cy.get('select[name=transporteur]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.select2Ajax('transporteur', 'GT', 'modalEditChauffeur', false, '/chauffeur/autocomplete*')
        cy.get('button#submitEditChauffeur').click().wait('@chauffeur_edit');
        cy.get('#modalEditChauffeur').should('not.be.visible');
        cy.get('#tableChauffeur_id tbody td').contains('Georgines').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('Georgines');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Marceline');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('10');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('GT');
        });
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
        cy.get(`.btn-group`).click();
        cy.get('#modalTransporteur').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalTransporteur input[name=label]').should('be.visible').then(() => {
                cy.get('#modalTransporteur input[name=label]').type('WIILOG');
                cy.get('#modalTransporteur input[name=code]').type(' WIILOG');
                cy.get(`button[type='submit']`).click().wait(['@transporteur_save', '@transporteur_api']);
            })
        })
        cy.get('#modalTransporteur').should('not.be.visible');
        cy.wait('@transporteur_api');
        cy.get('#tableTransporteur_id tbody td').contains('WIILOG').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('WIILOG');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('WIILOG');
        });
    })

    it('should edit a transporter', () => {
        cy.intercept('POST', 'transporteur/save?*').as('transporteur_save_edit');
        cy.wait('@transporteur_api');
        cy.get('#tableTransporteur_id tbody td').contains('TRANSPORTEUR').click();
        cy.get('#modalTransporteur').should('be.visible');
        cy.get('#modalTransporteur input[name=label]').click().clear().type('LA POSTE');
        cy.get('#modalTransporteur input[name=code]').click().clear().type('LA POSTE');
        cy.get(`button[type='submit']`).click().wait('@transporteur_save_edit');
        cy.get('#modalTransporteur').should('not.be.visible');
        cy.wait('@transporteur_api');
        cy.get('#tableTransporteur_id tbody td').contains('LA POSTE').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('LA POSTE');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('LA POSTE');
        });
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
        cy.get(`button[data-target='#modalNewNature']`).click();
        cy.get('#modalNewNature').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewNature input[name=label]').should('be.visible').then(() => {
                cy.get('#modalNewNature input[name=label]').eq(0).type('BAC');
                cy.get('#modalNewNature input[name=code]').type(' BAC');
                cy.get('#modalNewNature input[name=quantity]').type('0');
                cy.get(`button#submitNewNature`).click().wait(['@nature_new', '@nature_api']);
            })
        })
        cy.get('#modalNewNature').should('not.be.visible');
        cy.wait('@nature_api');
        cy.get('#tableNatures tbody td').contains('BAC').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('BAC');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('BAC');
        });
    })

    it('should edit a nature', () => {
        cy.intercept('POST', 'nature-unite-logistique/modifier').as('nature_edit');
        cy.wait('@nature_api');
        cy.get('#tableNatures tbody td').contains('NATURE').click();
        cy.get('#modalEditNature').should('be.visible');
        cy.get('#modalEditNature input[name=label]').eq(0).click().clear().type('COLIS');
        cy.get('#modalEditNature input[name=code]').click().clear().type('COLIS');
        cy.get('#modalEditNature input[name=quantity]').click().clear().type('10');
        cy.get(`button#submitEditNature`).click().wait('@nature_edit');
        cy.get('#modalEditNature').should('not.be.visible');
        cy.wait('@nature_api');
        cy.get('#tableNatures tbody td').contains('COLIS').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('COLIS');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('COLIS');
        });
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
        cy.get(`a[data-target='#modalNewVehicle']`).click();
        cy.get('#modalNewVehicle').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewVehicle input[name=registrationNumber]').should('be.visible').then(() => {
                cy.get('#modalNewVehicle input[name=registrationNumber]').type('CL-010-RA');
                cy.get(`.modal-footer button`).eq(1).click().wait(['@vehicule_new', '@vehicule_api']);
            })
        })
        cy.get('#modalNewVehicle').should('not.be.visible');
        cy.wait('@vehicule_api');
        cy.get('#vehicleTable_id tbody td').contains('CL-010-RA').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('CL-010-RA');
        });
    })

    it('should edit a vehicle', () => {
        cy.intercept('POST', 'vehicule/edit').as('vehicle_edit');
        cy.wait('@vehicule_api');
        cy.get('#vehicleTable_id tbody td').contains('VEHICULE').click();
        cy.get('#modalEditVehicle').should('be.visible');
        cy.get('#modalEditVehicle input[name=registrationNumber]').click().clear().type('AA-000-AA');
        cy.get(`button#submitEditVehicle`).click().wait('@vehicle_edit');
        cy.get('#modalEditVehicle').should('not.be.visible');
        cy.wait('@vehicule_api');
        cy.get('#vehicleTable_id tbody td').contains('AA-000-AA').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('AA-000-AA');
        });
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
        cy.get(`button[data-toggle='modal']`).click();
        cy.get('#modalNewProject').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewProject input[name=code]').should('be.visible').then(() => {
                cy.get('#modalNewProject input[name=code]').type('GAZ');
                cy.select2Ajax('projectManager', 'Admin');
                cy.get(`#modalNewProject button[type=submit]`).click().wait(['@project_new', '@project_api']);
            })
        })
        cy.get('#modalNewProject').should('not.be.visible');
        cy.wait('@project_api');
        cy.get('#projectTable_id tbody td').contains('GAZ').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('GAZ');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Admin');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('Oui');
        });
    })

    it('should edit a project', () => {
        cy.intercept('POST', 'project/edit').as('project_edit');
        cy.wait('@project_api');
        cy.get('#projectTable_id tbody td').contains('PROJET').click();
        cy.get('#modalEditProject').should('be.visible');
        cy.get('#modalEditProject input[name=code]').click().clear().type('RACLETTE');
        cy.get('select[name=projectManager]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.select2Ajax('projectManager', 'Lambda', 'modalEditProject', false)
        cy.get(`button#submitEditProject`).click().wait('@project_edit');
        cy.get('#modalEditProject').should('not.be.visible');
        cy.wait('@project_api');
        cy.get('#projectTable_id tbody td').contains('RACLETTE').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('RACLETTE');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Lambda');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('Oui');
        });
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
        cy.get('#modalNewCustomer').should('be.visible', {timeout: 8000}).then(() => {
            cy.get('#modalNewCustomer input[name=name]').should('be.visible').then(() => {
                cy.get('#modalNewCustomer [name=name]').type('LAPEL');
                cy.get('#modalNewCustomer [name=address]').type('Bègles');
                cy.get('#modalNewCustomer [name=recipient]').type('PAS');
                cy.get('#modalNewCustomer [name=phone-number]').type('0218923090');
                cy.get('#modalNewCustomer [name=email]').type('cypress@wiilog.fr');
                cy.get('#modalNewCustomer [name=fax]').type('0218923091');
                cy.get(`#modalNewCustomer button[type=submit]`).click().wait(['@customer_new', '@customer_api']);
            })
        })
        cy.get('#modalNewCustomer').should('not.be.visible');
        cy.wait('@customer_api');
        cy.get('#customerTable tbody td').contains('LAPEL').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('LAPEL');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Bègles');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('PAS');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('0218923090');
            cy.wrap(td).parent('tr').find('td').eq(5).contains('cypress@wiilog.fr');
            cy.wrap(td).parent('tr').find('td').eq(6).contains('0218923091');
        });
    })

    it('should edit a customer', () => {
        cy.intercept('POST', 'clients/edit').as('customer_edit');
        cy.wait('@customer_api');
        cy.get('#customerTable tbody td').contains('Client').click();
        cy.get('#modalEditCustomer').should('be.visible');
        cy.get('#modalEditCustomer input[name=name]').clear().click().type('RE');
        cy.get('#modalEditCustomer [name=address]').clear().click().type('Bordeaux');
        cy.get('#modalEditCustomer [name=recipient]').clear().click().type('POND');
        cy.get('#modalEditCustomer [name=phone-number]').clear().click().type('0218923092');
        cy.get('#modalEditCustomer [name=email]').clear().click().type('cypress_bis@wiilog.fr');
        cy.get('#modalEditCustomer [name=fax]').clear().click().type('0218923093');
        cy.get(`button#submitEditCustomer`).click().wait('@customer_edit');
        cy.get('#modalEditCustomer').should('not.be.visible');
        cy.wait('@customer_api');
        cy.get('#customerTable tbody td').contains('RE').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('RE');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Bordeaux');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('POND');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('0218923092');
            cy.wrap(td).parent('tr').find('td').eq(5).contains('cypress_bis@wiilog.fr');
            cy.wrap(td).parent('tr').find('td').eq(6).contains('0218923093');
        });
    })
})
