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
Cypress.Commands.add('navigateInNavMenu', (menu, subMenu) => {
    cy
        .get('nav')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(` .dropdown-item-sub .wii-icon-${menu}`)
        .click()
        .parents('.dropdown-item-sub')
        .siblings('.dropdown-menu.dropdown-menu-sub')
        .should('be.visible')
        .get(`.dropdown-item[data-cy-nav-item="${subMenu}"]`).first()
        .click();
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
        .click()
        .parents()
        .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
        .type(value)
        .wait('@select2Request', {timeout: 10000})
        .its('response.statusCode').should('eq', 200)
        .then(() => {
            cy.get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
                .parents('.select2-dropdown')
                .find('.select2-results__option')
                .first()
                .click({waitForAnimations: false, multiple: true})
                .then(() => {
                    cy.get(`[name=${selectName}]`).find('option:selected').should('have.length', 1);
                });
        })
})

Cypress.Commands.add('select2AjaxMultiple', (selectName, value) => {
    cy.intercept('GET', '/select/*').as('select2Request');
    value.forEach(element => {
        cy.get(`[name=${selectName}]`)
            .siblings('.select2')
            .click()
            .parents()
            .get(`input[type=search][aria-controls^=select2-${selectName}-][aria-controls$=-results]`)
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
