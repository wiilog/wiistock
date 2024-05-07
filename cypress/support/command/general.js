Cypress.Commands.add('navigateInNavMenu', (menu, subMenu) => {
    cy
        .get('nav')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(`[data-cy-container="${menu}"]`)
        .click()
        .then((element) => {
            if (subMenu === undefined) {
                return;
            }
            cy.get(`nav [data-cy-icon="${menu}"`)
                .parents('.dropdown-item-sub')
                .siblings('.dropdown-menu.dropdown-menu-sub')
                .should('be.visible')
                .get(`.dropdown-item[data-cy-nav-item="${subMenu}"]`).first()
                .click();
        })
})
