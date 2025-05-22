
describe('Test global setup', () => {

    it('Test if owasp container is enabled', () => {
        cy.visit('/.env');
    });
});
