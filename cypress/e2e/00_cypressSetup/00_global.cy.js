
describe('Test cypress setup', () => {

    it('Test if owasp container is enabled', () => {
        cy.visit('/.env', {failOnStatusCode: false}).contains("403");
    });
});
