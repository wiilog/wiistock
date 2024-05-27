const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

function isLinkAndIsAvailable($el, queueLink, visitedLinks) {
    return $el.attr('href') !== undefined && !isAlreadyInStack(queueLink,$el) && !visitedLinks.includes($el.attr('href'));
}

function isDivAndIsAvailable($el, visitedLinksText) {
    return $el.attr('href') === undefined && !visitedLinksText.includes($el.text());
}

function isAlreadyInStack(queueLink, $el) {
    return queueLink.includes($el.attr('href'));
}

/*
    * Visit a link and check if the request has a 500 status code
    * @param {string} link
    * @return {void}
 */
function visiteLink(link) {
    cy.visit(link)
    cy.log("Currenlty visiting : " + link)
    cy.wait('@request').its('response.statusCode').should('not.eq', 500)
}

/**
 * Visit a div and check if the request has a 500 status code
 * @param $el {Object}
 * @return {void}
 */
function visiteDiv($el) {
    $el.click()
    cy.wait('@request').its('response.statusCode').should('not.eq', 500)
}

function navigateInMenu(menuPage, subMenu) {
    cy.navigateInNavMenu(menuPage.menu, subMenu)
    // request should not have 500 status code
    cy.wait('@request').its('response.statusCode').should('not.eq', 500)
}

describe('Open all pages', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.intercept('*').as('request');
        uncaughtException();
    })

    it('Pages from menu', () => {
        const queueLink = []
        const visitedLinks = []

        cy.get('#main-nav').click().then(() => {
            cy
                .get('[data-cypress="menu"]')
                .children(':visible:not(a)')
                .each(($el) => {
                    cy
                        .wrap($el)
                        .click()
                        .find('.dropdown-menu')
                        .children()
                        .each(($child) => {
                            if(isLinkAndIsAvailable($child, queueLink, visitedLinks)) {
                                queueLink.push($child.attr('href'))
                            }
                        })
                }).then(() => {
                queueLink.forEach(link => {
                    visitedLinks.push(link)
                    visiteLink(link)
                })
            })
        })
    })

    it('Pages from setting', () => {
        cy.navigateInNavMenu('parametre')
        cy.wait('@request').its('response.statusCode').should('not.eq', 500)

        let queueLink = []
        let visitedLinks = []
        // used for pages openned by clicking on a <div> instead of a <a>
        let visitedLinksText = []
        const excludedLinks = [
            '/parametrage-global/dashboard/',
            '/parametrage/mobile-app-link',
            '/parametrage/wiispool-link'
        ]

        // init stackLink with the first page of setting menu
        cy.get('.settings-menu a').each(($el) => {
            // exclude some links (like dashboard because they don't have the same structure)
            if(!excludedLinks.includes($el.attr('href'))) {
                queueLink.push($el.attr('href'))
            }
        }).then(() => {
            // loop on stackLink to open all pages
            while (queueLink.length > 0) {
                // get the first link of the stack and remove it
                let link = queueLink.shift()
                visitedLinks.push(link)

                // visit the link
                visiteLink(link);

                // open all pages in the left menu of the current page (add them to the stack only if there is a link and is not already in the stack)
                cy.get('.settings-item').each(($el) => {
                    // add the link to the stack if it is not already in the stack and if it is a link
                    if(isLinkAndIsAvailable($el, queueLink, visitedLinks)) {
                        queueLink.push($el.attr('href'))
                    }
                    else if(isDivAndIsAvailable($el, visitedLinksText)) {
                        visitedLinksText.push($el.text())
                        visiteDiv($el);
                    }
                    else {
                        cy.log("This link is already treated : " + $el.attr('href') + " or " + $el.text())
                    }
                })
            }
        })
    })

    it('Pages from plus menu', () => {
        let plusPages = []
        cy.get('.quick-plus').click().then(() => {
            cy.get('#quick-menu a').each(($el) => {
                plusPages.push($el.attr('href'))
            }).then(() => {
                plusPages.forEach((plusPage) => {
                    cy.visit(plusPage)
                    // request should not have 500 status code
                    cy.wait('@request').its('response.statusCode').should('not.eq', 500)
                    // verfify we have div with modal class
                    cy.get('[data-modal-type="new"]').should('be.visible');
                })
            })
        })
    })
})
