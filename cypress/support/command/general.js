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
        });
});

/**
 * Allows us the possibility to make a request from the + button.
 * @param request The name of the request item.
 */
Cypress.Commands.add('navigateInQuickMoreMenu', (request) => {
    cy
        .get('.quick-plus')
        .click()
        .get('.dropdown-menu')
        .should('be.visible')
        .get(`[data-cy-request-item="${request}"]`)
        .click()
        .wait(1000);
});

/**
 * Allows us to verify the comment in the show page.
 * @param comment Comment expected.
 */
Cypress.Commands.add('verifyComment', (comment) => {
    cy
        .get('.comment-container')
        .find('p')
        .invoke('text')
        .then((text) => {
            expect(text).to.equal(comment)
        });
})

/**
 * Allows us to verify the status in the show page.
 * @param status Status expected.
 */
Cypress.Commands.add('verifyStatus', (status) => {
    cy
        .get('.timeline')
        .find('strong')
        .invoke('text')
        .then((text) => {
            expect(text).to.equal(status)
        });
})

/**
 * Allows us to use the dropdown dropright button to delete or edit in show page.
 * @param action The action you want to do.
 */
Cypress.Commands.add('dropdownDroprightAction', (action) => {
    cy
        .get('.dropright.dropdown')
        .click()
        .find(`span[title=${action}]`)
        .click()
        .wait(200);
})

/**
 * Check if the result code status of a request is equal to a status code.
 * @param request Request to wait.
 * @param statusCode Status code expected.
 */
Cypress.Commands.add('checkRequestStatusCode', (request, statusCode) => {
    cy
        .wait(`@${request}`)
        .its('response.statusCode')
        .should('eq', statusCode);
})
