import {uncaughtException} from "/cypress/support/utils";

describe('Setup the environment', () => {
    it('Reset the db', () => {
        cy.startingCypressEnvironnement(false)
        //TODO METTRE EN TRUE POUR LA MISE EN PLACE SUR LA BASE PRÉSENTE DANS LE FTP
        uncaughtException();
    });
})
