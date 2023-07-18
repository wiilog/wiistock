const user= Cypress.config('user');
describe('Edit and save components in Referentiel > Fournisseur', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'supplier_index');
    })

    it('should add a new supplier', () => {
        cy.intercept('POST', 'fournisseur/creer').as('supplier_new');
        cy.get(`button[data-target='#modalNewFournisseur']`).click();
        cy.get('#modalNewFournisseur').should('be.visible');
        cy.get('#modalNewFournisseur input[name=name]').type('RENAULT');
        cy.get('#modalNewFournisseur input[name=code]').type('RENAULT');
        cy.get('#modalNewFournisseur input[name=possibleCustoms]').check();
        cy.get('#modalNewFournisseur input[name=urgent]').check();
        cy.get('button#submitNewFournisseur').click().wait('@supplier_new');
        cy.get('#supplierTable_id tbody td').contains('RENAULT').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('RENAULT');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('RENAULT');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('oui');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('oui');
        });
    })

    it ('should edit a supplier', () => {
        cy.intercept('POST', 'fournisseur/modifier').as('supplier_edit');
        cy.get('#supplierTable_id tbody td').contains('FOURNISSEUR').click();
        cy.get('#modalEditFournisseur').should('be.visible');
        cy.get('#modalEditFournisseur input[name=name]').click().clear().type('IKEA');
        cy.get('#modalEditFournisseur input[name=code]').click().clear().type('IKEA');
        cy.get('#modalEditFournisseur input[name=possibleCustoms]').check();
        cy.get('#modalEditFournisseur input[name=urgent]').check();
        cy.get('button#submitEditFournisseur').click().wait('@supplier_edit');
        cy.get('#supplierTable_id tbody td').contains('IKEA').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('IKEA');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('IKEA');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('oui');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('oui');
        });
    })
})

describe('Edit and save components in Referentiel > Emplacements', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'emplacement_index');
    })

    it('should add a new location', () => {
        cy.intercept('POST', 'emplacement/creer').as('emplacement_new');
        cy.get(`button[data-toggle='modal']`).filter(':visible').click();
        cy.get('#modalNewEmplacement').should('be.visible');
        cy.get('#modalNewEmplacement input[name=label]').type('STOCK');
        cy.get('#modalNewEmplacement input[name=description]').type('Non défini');
        cy.select2Ajax('zone', 'Activité Standard')
        cy.get('button#submitNewEmplacement').click().wait('@emplacement_new');
        cy.get('#locationsTable tbody td').contains('STOCK').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('STOCK');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non défini');
        });
    })

    it ('should edit a location', () => {
        cy.intercept('POST', 'emplacement/edit').as('emplacement_edit');
        cy.get('#locationsTable tbody td').contains('EMPLACEMENT').click();
        cy.get('#modalEditEmplacement').should('be.visible');
        cy.get('#modalEditEmplacement input[name=label]').click().clear().type('LABO');
        cy.get('#modalEditEmplacement input[name=description]').click().clear().type('Cypress');
        cy.get('button#submitEditEmplacement').click().wait('@emplacement_edit');
        cy.get('#locationsTable tbody td').contains('BUREAU WII').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('BUREAU WII');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Cypress');
        });
    })

    it('should add a new group', () => {
        cy.intercept('POST', 'emplacements/groupes/creer').as('location_group_new');
        cy.get('.nav-item').eq(1).click();
        cy.get(`button[data-toggle='modal']`).filter(':visible').click();
        cy.get('#modalNewLocationGroup').should('be.visible');
        cy.get('#modalNewLocationGroup input[name=label]').type('SITE A');
        cy.get('#modalNewLocationGroup [name=description]').type('Non défini');
        cy.get(`#modalNewLocationGroup [data-title='Statut'] input`).eq(0).click();
        cy.select2AjaxMultiple('locations', ['LABO 11', 'ZONE 41']);
        cy.get('button#submitNewLocationGroup').click().wait('@location_group_new');
        cy.get('#groupsTable tbody td').contains('SITE A').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('SITE A');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non défini');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('Actif');
            cy.wrap(td).parent('tr').find('td').eq(5).contains('2');
        });
    })

    it ('should edit a group', () => {
        cy.intercept('POST', 'emplacements/groupes/modifier').as('location_group_edit');
        cy.get('.nav-item').eq(1).click();
        cy.get('#groupsTable tbody td').contains('GROUPE').click();
        cy.get('#modalEditLocationGroup').should('be.visible');
        cy.get('#modalEditLocationGroup input[name=label]').click().clear().type('TERRAIN A');
        cy.get('#modalEditLocationGroup [name=description]').click().clear().type('Cypress');
        cy.get(`#modalEditLocationGroup [data-title='Statut'] input`).eq(1).click();
        cy.get('select[name=locations]')
            .siblings('.select2')
            .find('.select2-selection__choice__remove')
            .click();
        cy.select2AjaxMultiple('locations', ['LABO 11', 'ZONE 41'], 'modalEditLocationGroup');
        cy.get('button#submitEditLocationGroup').click().wait('@location_group_edit');
        cy.get('#groupsTable tbody td').contains('TERRAIN A').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(2).contains('TERRAIN A');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Cypress');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('Inactif');
            cy.wrap(td).parent('tr').find('td').eq(5).contains('2');
        });
    })

    it('should add a new zone', () => {
        cy.intercept('POST', 'zones/creer').as('zone_new');
        cy.get('.nav-item').eq(2).click();
        cy.get(`button[data-toggle='modal']`).filter(':visible').click();
        cy.get('#modalNewZone').should('be.visible');
        cy.get('#modalNewZone input[name=name]').type('COMMODE');
        cy.get('#modalNewZone [name=description]').type('Non défini');
        cy.get(`#modalNewZone [data-title='Statut*'] input`).eq(1).click({force: true});
        cy.get(`#modalNewZone button[type='submit']`).click().wait('@zone_new');
        cy.get('#zonesTable tbody td').contains('COMMODE').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('COMMODE');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Non défini');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non');
        });
    })

    it ('should edit a zone', () => {
        cy.intercept('POST', 'zones/modifier').as('zone_edit');
        cy.get('.nav-item').eq(2).click();
        cy.get('#zonesTable tbody td').contains('ZONE').click();
        cy.get('#modalEditZone').should('be.visible');
        cy.get('#modalEditZone input[name=name]').click().clear().type('BAT 123');
        cy.get('#modalEditZone [name=description]').click().clear().type('Cypress');
        cy.get(`#modalEditZone [data-title='Statut*'] input`).eq(1).click({force: true});
        cy.get('button#submitEditZone').click().wait('@zone_edit');
        cy.get('#zonesTable tbody td').contains('BAT 123').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('BAT 123');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Cypress');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('Non');
        });
    })
})

describe('Edit and save components in Referentiel > Chauffeurs', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'chauffeur_index');
    })

    it('should add a new driver', () => {
        cy.intercept('POST', 'chauffeur/creer').as('chauffeur_new');
        cy.intercept('POST', 'chauffeur/api').as('chauffeur_api');
        cy.get(`a[data-target='#modalNewChauffeur']`).click();
        cy.get('#modalNewChauffeur').should('be.visible');
        cy.get('#modalNewChauffeur input[name=nom]').type('Troijours');
        cy.get('#modalNewChauffeur input[name=prenom]').type(' Adam');
        cy.get('#modalNewChauffeur input[name=documentID]').type('2');
        cy.select2Ajax('transporteur', 'DHL', '','/chauffeur/autocomplete*')
        cy.get('button#submitNewChauffeur').click().wait(['@chauffeur_new', '@chauffeur_api']);
        cy.get('#tableChauffeur_id tbody td').contains('Troijours').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('Troijours');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Adam');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('2');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('DHL');
        });
    })

    it ('should edit a driver', () => {
        cy.intercept('POST', 'chauffeur/modifier').as('chauffeur_edit');
        cy.intercept('POST', 'chauffeur/api').as('chauffeur_api');
        cy.get('#tableChauffeur_id tbody td').contains('Chauffeur').click();
        cy.get('#modalEditChauffeur').should('be.visible');
        cy.get('#modalEditChauffeur input[name=nom]').click().clear().type('Georgines');
        cy.get('#modalEditChauffeur input[name=prenom]').click().clear().type('Marceline');
        cy.get('#modalEditChauffeur input[name=documentID]').click().clear().type('10');
        cy.get('select[name=transporteur]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.select2Ajax('transporteur', 'GT', 'modalEditChauffeur', '/chauffeur/autocomplete*')
        cy.get('button#submitEditChauffeur').click().wait(['@chauffeur_edit', '@chauffeur_api']);
        cy.get('#tableChauffeur_id tbody td').contains('Georgines').then((td) => {
            cy.wrap(td).parent('tr').find('td').eq(1).contains('Georgines');
            cy.wrap(td).parent('tr').find('td').eq(2).contains('Marceline');
            cy.wrap(td).parent('tr').find('td').eq(3).contains('10');
            cy.wrap(td).parent('tr').find('td').eq(4).contains('GT');
        });
    })
})
