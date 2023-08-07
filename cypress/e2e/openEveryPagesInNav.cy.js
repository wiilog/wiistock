const tableRoleName = 'tableRoles';
const user = {
    email: 'Test@test.fr',
    password: 'Test123456!',
}
describe('', () => {
    beforeEach(() => {
        cy.login(user.email, user.password);
    })

    it('should get all permissions', () => {
        cy.intercept('POST', '/parametrage/users/api-modifier').as('user_api_edit');
        cy.intercept('POST', '/parametrage/utilisateurs/roles/api').as('settings_role_api');
        cy.visit('/');
        cy.get('[data-cy-user-header-button]').click().wait('@user_api_edit');
        cy.get('#modalEditUser [name=role] option').first().then(($option) => {
            const roleName = $option.text();
            const roleId = $option.val();
            cy.log(roleName, roleId);
            cy.get('#modalEditUser button.close').click();
            cy.navigateInNavMenu('parametre');
            cy.get('[data-cy-settings-menu=roles]').click().wait('@settings_role_api');
            cy.get(`input[type=search][aria-controls=${tableRoleName}]`).click().type(`${roleName}{enter}`);
            cy.get(`table#${tableRoleName} tbody tr`).contains(roleName).first().click();
            cy.url().should('include', roleId)
            cy.get('.settings-content input[type=checkbox]').then(($inputs) => {
                $inputs.prop("checked", true);
                cy.get('.save-settings').click({force: true});
            })
        })
    })

    it('should navigate all pages', () => {
        cy.visit('/')
            .get('nav')
            .click()
            .get('.dropdown-menu')
            .should('be.visible')
            .get('.dropdown-menu a.dropdown-item')
            .each(($navLink) => {
                cy
                    .request({
                        url: $navLink.prop('href'),
                        failOnStatusCode: false,
                    })
                    .should((response) => {
                        expect(response.status).to.eq(200);
                    })
            })
            .then(() => {
                Cypress.session.clearAllSavedSessions()
            });
    })
})
