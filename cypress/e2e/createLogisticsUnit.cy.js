describe('Create incoming logistics unit', () => {

    // run one time before all tests
    before(() => {
        //cy.logout();
        //var userCount = Date.now();
        //cy.register(userCount);
        //cy.login('Test@test.fr','Test123456!');
        //cy.statusAndRoleUpgrade(userCount);
        //cy.logout();
        //cy.login(`${userCount}@wiilog.fr`, `${userCount}W!!log`);
    })

    it("Create incoming logistics unit", () => {
        cy.visit('/accueil#1')
        cy.openMainMenu();
        cy.openItemMainMenu('Traçabilité');
        cy.openSecondItemMainMenu('/arrivage/');
        cy.openModal('Nouvel arrivage UL');
        cy.select2Ajax('fournisseur', 'ADVANCED');
        cy.select2('transporteur', 'Alexis');
        cy.select2('chauffeur', 'FAURE');
        cy.select2AjaxMultiple('noTracking', ['1234', '456']);
        cy.select2('numeroCommandeList', ['1234', '4567']);


        cy.select2Ajax('project', 'LABO 1');

        cy.get('label').contains('Palette').siblings('[name=packs]').clear().type('1');
        cy.get('label').contains('Demi palette').siblings('[name=packs]').clear().type('2');
        cy.get('label').contains('Bac').siblings('[name=packs]').clear().type('2');
        cy.get('label').contains('Colis').siblings('[name=packs]').clear().type('0');

        cy.get('#modalNewArrivage select[name="type"]').select('standard');

        // cy.get('#modalNewArrivage button').contains('Enregistrer').click()
        // cy.wait(10000);
    })
})
