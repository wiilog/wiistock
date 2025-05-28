
describe('Test cypress setup', () => {

    it('Test if owasp container is enabled', () => {
        //cy.visit('/.env', {failOnStatusCode: false}).contains("403");
        cy.visit('/.env', {failOnStatusCode: false}).as('env')
        cy.wait('@env').its('response.statusCode').should('eq', 403)
    });
});
