import routes, {interceptRoute} from "../../support/utils/routes";
const user = Cypress.config('user');

describe('Add and edit components in Referentiel > Emplacements', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
    })

    it('Open all pages', () => {

    })
})
