/*
const user = Cypress.config('user');
describe('Get the right permissions for movements added', () => {
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

describe('Test the filters', () => {
    beforeEach(() => {
        cy.intercept('POST', '/mouvement-traca/api').as('tracking_movement_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'mvt_traca_index');
        cy.get('select[name=tableMvts_length]').select(3).wait('@tracking_movement_api');
        cy.deleteAllFilters();
    })

    it('should sort array elements by a minimum date', () => {
        cy.get('#dateMin').click().clear().type('20/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 11);

        cy.get('#dateMin').click().clear().type('24/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 9);

        cy.get('#dateMin').click().clear().type('25/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('td').should('have.class', 'dataTables_empty')
    })

    it('should sort array elements by a maximum date', () => {
        cy.get('#dateMax').click().clear().type('18/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('td').should('have.class', 'dataTables_empty');

        cy.get('#dateMax').click().clear().type('22/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 2);

        cy.get('#dateMax').click().clear().type('25/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 11);
    })

    it('should sort array elements by a logistic unit', () => {
        cy.get('#ul').click().clear().type('123456789');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);

        cy.get('#ul').click().clear().type('00000000000');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('td').should('have.class', 'dataTables_empty');
    })

    it('should sort array elements by an article', () => {
        cy.select2Ajax('article', 'ART230700000001');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 2);
    })

    it('should sort array elements by a location', () => {
        cy.select2Ajax('emplacement', 'BUREAU GT', '', '/emplacement/!*');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 8);

        cy.get('select[name=emplacement]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();

        cy.select2Ajax('emplacement', 'ZONE 41', '', '/emplacement/!*');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('td').should('have.class', 'dataTables_empty');
    })

    it('should sort array elements by a type', () => {
        cy.select2('statut', 'depose');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '5');

        cy.get(`[name=statut]`)
            .siblings('.select2')
            .find('li .select2-selection__choice__remove')
            .click();

        cy.select2('statut', ['depose', 'passage à vide']);
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '6');
    })

    it('should sort array elements by an operator', () => {
        cy.select2AjaxMultiple('utilisateurs', ['Admin']);
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '10');

        cy.get(`[name=utilisateurs]`)
            .siblings('.select2')
            .find('li .select2-selection__choice__remove')
            .click();

        cy.select2AjaxMultiple('utilisateurs', ['Admin', 'Lambda']);
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '11');
    })

    it('should sort array elements by a minimum date and a maximum date', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('22/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '2');

        cy.get('#dateMin').click().clear().type('20/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('24/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '11');

        cy.get('#dateMin').click().clear().type('24/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('28/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', '9');

        cy.get('#dateMin').click().clear().type('16/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('18/07/2023{enter}');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('td').should('have.class', 'dataTables_empty');
    })

    it('should sort array elements by a minimum date, a maximum date and a logistic unit', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.get('#ul').click().clear().type('9999-8888');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);
    })

    it('should sort array elements by a minimum date, a maximum date and an article', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.select2Ajax('article', 'ART230700000002');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);
    })

    it('should sort array elements by a minimum date, a maximum date and a location', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.select2Ajax('emplacement', 'LABO 11', '', '/emplacement/!*');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);
    })

    it('should sort array elements by a minimum date, a maximum date and a type', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.select2('statut', 'prise');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 2);
    })

    it('should sort array elements by a minimum date, a maximum date and an operator', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.select2AjaxMultiple('utilisateurs', ['Admin']);
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 10);
    })

    it('should sort array elements by a minimum date, a maximum date, a logistic unit, a location, a type and an operator', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.get('#ul').click().clear().type('12345');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 6);
        cy.select2Ajax('emplacement', 'BUREAU GT', '', '/emplacement/!*');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 4);
        cy.select2('statut', 'passage à vide');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);
        cy.select2AjaxMultiple('utilisateurs', ['Lambda']);
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('td').should('have.class', 'dataTables_empty');
    })

    it('should sort array elements by a minimum date, a maximum date, an article, a location, a type and an operator', () => {
        cy.get('#dateMin').click().clear().type('18/07/2023{enter}');
        cy.get('#dateMax').click().clear().type('26/07/2023{enter}');
        cy.select2Ajax('article', 'ART230700000001');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 2);
        cy.select2Ajax('emplacement', 'BUREAU GT', '', '/emplacement/!*');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 2);
        cy.select2('statut', 'dépose dans UL');
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);
        cy.select2AjaxMultiple('utilisateurs', ['Admin']);
        cy.get('button.filters-submit').click().wait('@tracking_movement_api');
        cy.get('#tableMvts tbody').find('tr').should('have.length', 1);
    })

    it(`shouldn't sort array elements`, () => {
        cy.deleteAllFilters();
    })
})

describe('Edit different types movements', () => {
    beforeEach(() => {
        cy.intercept('POST', '/mouvement-traca/modifier').as('mvt_traca_edit');
        cy.intercept('POST', '/mouvement-traca/api').as('tracking_movement_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'mvt_traca_index');
        cy.get('select[name=tableMvts_length]').select(3).wait('@tracking_movement_api');
    })

    it(`should edit a movement and check it with 'prise' type`, () => {
        cy.editMovement('00', 'prise');
    })

    it(`should edit a movement and check it with 'depose' type`, () => {
        cy.editMovement('01', 'depose');
    })

    it(`should edit a movement and check it with 'prises et deposes' type`, () => {
        cy.editMovement('02', 'depose');
        cy.editMovement('02', 'prise');
    })

    it(`should edit a movement and check it with 'groupage' type`, () => {
        cy.editMovement('03', 'groupage');
        cy.editMovement('03', 'groupage');
    })

    it(`should edit a movement and check it with 'passage à vide' type`, () => {
        cy.editMovement('04', 'passage à vide');
    })

    it(`should edit a movement and check it with 'dépose dans UL' type`, () => {
        cy.editMovement('05', 'dépose dans UL');
        cy.editMovement('05', 'depose');
    })
})
describe('Create movements with different type', () => {
    beforeEach(() => {
        cy.intercept('POST', '/mouvement-traca/creer').as('mvt_traca_new');
        cy.intercept('POST', '/mouvement-traca/api').as('tracking_movement_api');
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'mvt_traca_index');
    })

    it(`should add a new movement and check it with 'prise' type`, () => {
        cy.addMovement(1, 'prise');
    })

    it(`should add a new movement and check it with 'depose' type`, () => {
        cy.addMovement(2, 'depose');
    })

    it(`should add a new movement and check it with 'prises et deposes' type`, () => {
        cy.addMovement(3, 'prises et deposes');
    })

    it(`should add a new movement and check it with 'groupage' type`, () => {
        cy.addMovement(4, 'groupage');
    })

    it(`should add a new movement and check it with 'passage à vide' type`, () => {
        cy.addMovement(5, 'passage à vide');
    })

    it(`should add a new movement and check it with 'dépose dans UL' type`, () => {
        cy.addMovement(6, 'dépose dans UL');
    })
})
*/
