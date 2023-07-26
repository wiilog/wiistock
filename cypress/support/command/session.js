Cypress.Commands.add('register', (username, email, password  ) => {
    cy.visit('/register');
    cy.get('[name="utilisateur[username]"]').type(username);
    cy.get('[name="utilisateur[email]"]').type(email);
    cy.get('[name="utilisateur[plainPassword][first]"]').type(password);
    cy.get('[name="utilisateur[plainPassword][second]"]').type(password);
    cy.get('button[type=submit]').click();
    cy.url().should('contain', '/login')
})

Cypress.Commands.add('login', (email, password) => {
    cy.session([email, password], () => {
        cy.visit('/login');
        cy.get('[name=_username]').type(email);
        cy.get('[name=_password]').type(password);
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
