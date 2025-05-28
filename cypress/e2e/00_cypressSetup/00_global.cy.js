
describe('Test cypress setup', () => {

    it('Test if owasp container is enabled', () => {
        //cy.visit('/.env', {failOnStatusCode: false}).contains("403");

        cy.request('/.env', {failOnStatusCode: false})
            .then((resp) => {
                expect(resp.status).to.eq(301);
            });
    });
});
