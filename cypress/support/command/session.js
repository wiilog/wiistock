Cypress.Commands.add('register', (username, email, password  ) => {
    cy.visit('/register');
    cy.get('[name="utilisateur[username]"]').type(username);
    cy.get('[name="utilisateur[email]"]').type(email);
    cy.get('[name="utilisateur[plainPassword][first]"]').type(password);
    cy.get('[name="utilisateur[plainPassword][second]"]').type(password);
    cy.get('button[type=submit]').click();
    cy.url().should('contain', '/login')
})

Cypress.Commands.add('login', (user) => {
    cy.session([user.email, user.password], () => {

        cy.visit('/login', {failOnStatusCode: false});

        cy.get('[name=_username]').type(user.email);
        cy.get('[name=_password]').type(user.password);
        cy.get('button[type=submit]').click();
        cy.url().should('not.contain', '/login');
    },{
        validate() {
            cy.visit('/').url().should('not.contain', '/login');
        }
    })
})
Cypress.Commands.add('logout', () => {
    cy.navigateInNavMenu('deco');
    Cypress.session.clearCurrentSessionData();
})
