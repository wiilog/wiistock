let previousNumberOfCalls = 0;

Cypress.Commands.add('interceptAllRequets', () => {
    cy.intercept({resourceType: /script|document/}, {log: false}).as('allRequests');
    let numberOfCalls = 0;
    cy.get('@allRequests.all').then((calls) => {
        numberOfCalls = calls.length - previousNumberOfCalls;
        previousNumberOfCalls = calls.length;
        for (let i = 0; i < numberOfCalls; i++) {
            cy.wait('@allRequests', {timeout: 80000}).then((interceptions) => {
                const interceptedResponses = [interceptions];
                interceptedResponses.forEach((interception) => {
                    expect(interception.response.statusCode).not.to.eq(500);
                });
            });

        }
    })
})



