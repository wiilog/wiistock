import routes, {interceptRoute} from "/cypress/support/utils/routes";
import {uncaughtException} from "/cypress/support/utils";

const user = Cypress.config('user');
const filterItems = {
    multipleTypes: 'standard',
    requesters: 'Admin',
    manufacturingOrderNumber: '8',
    productArticleCode: '88',
    dropLocation: 'BUREAU GT',
    emergencyMultiple: 'Urgence',
    date: '10/06/2024'
};

describe('Filter the production request datatable', () => {
    beforeEach(() => {
        uncaughtException();
        interceptRoute(routes.production_api);
        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('menu-production', 'production_request_index');
    });

    afterEach( () => {
        cy.get('button.clearFiltersBtn').click();
    });

    it('Check if the clear filters button work correctly', () => {
        cy.get('div.filters-container input[name=manufacturingOrderNumber').type(filterItems.manufacturingOrderNumber);

        cy.get('button.clearFiltersBtn').click();

        cy.checkRequestStatusCode('production_api', 200);

        cy
            .get('div.filters-container input[name=manufacturingOrderNumber')
            .invoke("text")
            .then( (text) => {
                expect(text).to.equal('');
            });
    });

    it('Check if one filter is filtering correctly', () => {
        cy.get('div.filters-container input[name=manufacturingOrderNumber]').type(filterItems['manufacturingOrderNumber']);

        cy.get('button.filters-submit').click();

        cy.checkRequestStatusCode('production_api', 200);

    });

    it('Check if all filters are filtering correctly', () => {
        cy.wait('@production_api');

        cy.get('input#dateMin').type(filterItems.date);

        cy.get('input#dateMax').type(filterItems.date);

        cy.fillSelectsInFiltersField(filterItems, ['emergencyMultiple', 'multipleTypes']);

        cy.fillInputsInFiltersField(filterItems);

        cy.checkElementsInSelectInFiltersField('div.filters-wrapper-row > div.statuses-filter', ['input[name=statuses-filter_107]']);

        cy.get('input[name=attachmentAssigned]').check({force: true});

        cy.get('button.filters-submit').click();

        cy.wait('@production_api').its('response.statusCode').should('eq', 200);
    });

});
