Cypress.Commands.add('register', (userCount) => {
    cy.visit('/register');
    cy.get('[name="utilisateur[username]"]').type(userCount.toString());
    cy.get('[name="utilisateur[email]"]').type(`${userCount}@wiilog.fr`);
    cy.get('[name="utilisateur[plainPassword][first]"]').type(`${userCount}W!!log`);
    cy.get('[name="utilisateur[plainPassword][second]"]').type(`${userCount}W!!log`);
    cy.get('button[type=submit]').click();
    cy.url().should('contain', '/login')
})

Cypress.Commands.add('login', (email, password) => {
    cy.visit('/login');
    cy.get('[name=_username]').type(email);
    cy.get('[name=_password]').type(password);
    cy.get('button[type=submit]').click();
    cy.url().should('contain', '/accueil#1')
})

Cypress.Commands.add('openMainMenu', () => {
    cy.get('nav').click()
    cy.get('nav div').should('have.attr', 'aria-expanded', 'true')
})

Cypress.Commands.add('openItemMainMenu', (textItem) => {
    cy.get(`[title=${textItem}]`).click();
})

Cypress.Commands.add('logout', () => {
    cy.openMainMenu();
    cy.openItemMainMenu('Déconnexion');
    Cypress.session.clearAllSavedSessions();
})

Cypress.Commands.add('openModalVerificate', (modalName) => {
    cy.get(`#${modalName}`).should('be.visible');
})

Cypress.Commands.add('select2Ajax', (selectName, value) => {
    cy.intercept('GET', '/select/*').as('select2Request');
    cy.get(`[name=${selectName}]`)
        .siblings('.select2')
        .click();

    cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .type(value)
        .wait('@select2Request')
        .its('response.statusCode').should('eq', 200)

    cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .click({waitForAnimations: false})
        .type('{enter}');

    cy.get(`[name=${selectName}]`).find('option:selected').should('have.length', 1);
})

Cypress.Commands.add('select2AjaxMultiple', (selectName, value) => {
    cy.intercept('GET', '/select/*').as('select2Request');

    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click();

        cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
            .type(element)
            .wait('@select2Request')
            .its('response.statusCode').should('eq', 200)
    })

    cy.get(`[name=${selectName}]`).find('option').should('have.length', value.length)
})

Cypress.Commands.add('select2', (selectName, value) => {
    const select = cy.get(`[name=${selectName}]`);
    if (!Array.isArray(value)) {
        value = [value];
    }
    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click()
            .type(`${element}{enter}`)
    })
    cy.get(`[name=${selectName}]`).then(($select) => {
        if ($select.hasOwnProperty('multiple')) {
            select.find('option').should('have.length', value.length)
        } else {
            select.find('option:selected').should('have.length', value.length)
        }
    })
})

Cypress.Commands.add('statusAndRoleUpgrade', (userCount) => {
    // TODO
    cy.openMainMenu();
    cy.openItemMainMenu('Paramétrage');
    cy.openItemSettings('Utilisateurs');
    cy.wait(5000);
    cy.searchInputType(`${userCount}{enter}`);
    cy.wait(10000)
    cy.get('table tr').eq(2).click();
    cy.wait(5000)
    cy.get('#modalEditUser input[value="1"]').click();
    cy.get('#modalEditUser [id^=select2-role-][id$=-container]').type('super admin');
    cy.wait(5000)
    cy.get('[id^=select2-role-][id$=-results] li').click()
    cy.get('#modalEditUser button').contains('Enregistrer').click()
})






