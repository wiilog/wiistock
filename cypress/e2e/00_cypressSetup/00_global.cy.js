
describe('Test cypress setup', () => {

    it('Test if owasp container is enabled', () => {
        cy
            .request({
                url: '/.env',
                followRedirect: false,
                failOnStatusCode: false,
            })
            .then((resp) => {
                expect(resp.status).to.eq(403);
            });
    });
});
