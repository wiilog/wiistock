const user = Cypress.config('user');
describe('Get the right permissions for next tests', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'mvt_traca_index');
    })

    it('should get the right settings', () => {
        cy.openSettingsItem('mouvements');
        cy.get(`input[name=CLOSE_AND_CLEAR_AFTER_NEW_MVT]`).check();
    })
})

describe('Edit different types movements', () => {

})
describe('Create movements with different type', () => {
    beforeEach(() => {
        cy.intercept('POST', '/mouvement-traca/creer').as('mvt_traca_new');
        cy.intercept('POST', '/mouvement-traca/api').as('tracking_movement_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'mvt_traca_index');
        cy.get(`button[data-target='#modalNewMvtTraca']`).click();
        cy.get(`#modalNewMvtTraca`)
            .should('be.visible');
        cy.get('input[name=datetime]').click().clear().type('21/07/2023 19:00{enter}');
        cy.get('select[name=operator]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.select2Ajax('operator', 'Lambda', 'modalNewMvtTraca', false);
    })

    it(`should add a new movement and check it with 'prise' type`, () => {
        cy.get('select[name=type]').select(1);
        cy.get('input[name=pack]').type('230714636-1055');
        cy.select2Ajax('emplacement', 'BUREAU GT', 'modalNewMvtTraca', true, '/emplacement/*')
        cy.get('#modalNewMvtTraca input[name=quantity]').clear().type('5');
        cy.get('#submitNewMvtTraca').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

        cy.get('#alert-modal').should('be.visible').then(() => {
            cy.get('#alert-modal button').click();
            cy.get(`#modalNewMvtTraca`)
                .should('be.visible');
        });
        cy.get('input[name=pack]').should('have.value', '');
        cy.get('select[name=emplacement]').siblings('.select2').should('have.value', '');
        cy.get('#modalNewMvtTraca input[name=quantity]').should('have.value', '1');
        cy.get('select[name=type]').invoke('val').should('be.null');

        cy.get('#modalNewMvtTraca button.close').click();
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(2).contains('21/07/2023 19:00');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(6).contains('5');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(7).contains('BUREAU GT');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('prise');
    })

    it(`should add a new movement and check it with 'depose' type`, () => {
        cy.get('select[name=type]').select(2);
        cy.get('input[name=pack]').type('230714636-1055');
        cy.select2Ajax('emplacement', 'BUREAU GT', 'modalNewMvtTraca', true, '/emplacement/*')
        cy.get('#modalNewMvtTraca input[name=quantity]').clear().type('5');
        cy.get('#submitNewMvtTraca').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

        cy.get('#alert-modal').should('be.visible').then(() => {
            cy.get('#alert-modal button').click();
            cy.get(`#modalNewMvtTraca`)
                .should('be.visible');
        });
        cy.get('input[name=pack]').should('have.value', '');
        cy.get('select[name=emplacement]').siblings('.select2').should('have.value', '');
        cy.get('#modalNewMvtTraca input[name=quantity]').should('have.value', '1');
        cy.get('select[name=type]').invoke('val').should('be.null');

        cy.get('#modalNewMvtTraca button.close').click();
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(2).contains('21/07/2023 19:00');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(6).contains('5');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(7).contains('BUREAU GT');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('depose');
    })

    it(`should add a new movement and check it with 'prises et deposes' type`, () => {
        cy.get('select[name=type]').select(3);
        cy.select2Ajax('emplacement-prise', 'ZONE 41', 'modalNewMvtTraca', true, '/emplacement/*');
        cy.select2('pack', '123456789-09876');
        cy.select2Ajax('emplacement-depose', 'BUREAU GT', 'modalNewMvtTraca', true, '/emplacement/*');
        cy.get('#modalNewMvtTraca input[name=quantity]').clear().type('5');
        cy.get('#submitNewMvtTraca').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

        cy.get('#alert-modal').should('be.visible').then(() => {
            cy.get('#alert-modal button').click();
            cy.get(`#modalNewMvtTraca`)
                .should('be.visible');
        });
        cy.get('select[name=emplacement-prise]').siblings('.select2').should('have.value', '');
        cy.get('select[name=pack]').siblings('.select2').should('have.value', '');
        cy.get('select[name=emplacement-depose]').siblings('.select2').should('have.value', '');
        cy.get('#modalNewMvtTraca input[name=quantity]').should('have.value', '1');
        cy.get('select[name=type]').invoke('val').should('be.null');

        cy.get('#modalNewMvtTraca button.close').click();
        for (let i = 0; i < 2; i++) {
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(2).contains('21/07/2023 19:00');
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(6).contains('5');
        }

        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(7).contains('BUREAU GT');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('depose');
        cy.get('#tableMvts tbody tr').eq(1).find('td').eq(7).contains('ZONE 41');
        cy.get('#tableMvts tbody tr').eq(1).find('td').eq(8).contains('prise');
    })

    it(`should add a new movement and check it with 'groupage' type`, () => {
        cy.get('select[name=type]').select(4);
        cy.get('input[name=parent]').type('55555-990');
        cy.select2('pack', '123456789-09876');
        cy.get('#submitNewMvtTraca').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

        cy.get('#alert-modal').should('be.visible').then(() => {
            cy.get('#alert-modal button').click();
            cy.get(`#modalNewMvtTraca`)
                .should('be.visible');
        });
        cy.get('select[name=pack]').siblings('.select2').should('have.value', '');
        cy.get('input[name=parent]').should('have.value', '');

        cy.get('#modalNewMvtTraca button.close').click();
        for (let i = 0; i < 2; i++) {
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(2).contains('21/07/2023 19:00');
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(5).contains('55555-990');
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(8).contains('groupage');
        }
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(6).contains('5');
        cy.get('#tableMvts tbody tr').eq(1).find('td').eq(6).contains('1');
    })

    it(`should add a new movement and check it with 'passage à vide' type`, () => {
        cy.get('select[name=type]').select(5);
        cy.get('input[name=pack]').type('230714636-1055');
        cy.select2Ajax('emplacement', 'BUREAU GT', 'modalNewMvtTraca', true, '/emplacement/*')
        cy.get('#submitNewMvtTraca').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

        cy.get('#alert-modal').should('be.visible').then(() => {
            cy.get('#alert-modal button').click();
            cy.get(`#modalNewMvtTraca`)
                .should('be.visible');
        });
        cy.get('input[name=pack]').should('have.value', '');
        cy.get('select[name=emplacement]').siblings('.select2').should('have.value', '');

        cy.get('#modalNewMvtTraca button.close').click();
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(2).contains('21/07/2023 19:00');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(6).contains('5');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(7).contains('BUREAU GT');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('passage à vide');
    })

    it(`should add a new movement and check it with 'dépose dans UL' type`, () => {
        cy.get('select[name=type]').select(6);
        cy.get('input[name=pack]').type('230714636-2000');
        cy.select2AjaxMultiple('articles', ['ART230700000002'], 'modalNewMvtTraca')
        cy.select2Ajax('emplacement', 'BUREAU GT', 'modalNewMvtTraca', true, '/emplacement/*')
        cy.get('#submitNewMvtTraca').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

        cy.get('#alert-modal').should('be.visible').then(() => {
            cy.get('#alert-modal button').click();
            cy.get(`#modalNewMvtTraca`)
                .should('be.visible');
        });
        cy.get('input[name=pack]').should('have.value', '');
        cy.get('select[name=articles]').siblings('.select2').should('have.value', '');
        cy.get('select[name=emplacement]').siblings('.select2').should('have.value', '');

        cy.get('#modalNewMvtTraca button.close').click();
        for (let i = 0; i < 4; i++) {
            if (i !== 3) {
                //TODO To check when the bug will be fixed (for the moment, the time displayed is the current time and not the recorded time)
                //cy.get('#tableMvts tbody tr').eq(i).find('td').eq(2).contains('21/07/2023 19:00');
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(3).contains('CHIMIE_REF');
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(4).contains('STOCK RIVES SILICIUM');
            }
            if (i !== 2) {
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(7).contains('BUREAU GT');
            }
            if (i === 1 || i === 3) {
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(8).contains('depose')
            }
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(6).contains('25');
        }

        cy.get('#tableMvts tbody tr').eq(2).find('td').eq(7).contains('LABO 11');
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('dépose dans UL');
        cy.get('#tableMvts tbody tr').eq(2).find('td').eq(8).contains('prise')
        cy.get('#tableMvts tbody tr').eq(3).find('td').eq(3).should('have.value', '');
        cy.get('#tableMvts tbody tr').eq(3).find('td').eq(4).should('have.value', '');
        cy.get('#tableMvts tbody tr').eq(3).find('td').eq(8).contains('depose')
    })

})
