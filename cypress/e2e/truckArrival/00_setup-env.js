import {uncaughtException} from "/cypress/support/utils";

describe('Setup the environment', () => {
    it('Reset the db', () => {
        cy.startingCypressEnvironnement(true);
        uncaughtException();
    });
})
