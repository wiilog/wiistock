const user = Cypress.config('user');

const menuPages = [
    {
        menu: 'traca',
        subMenu: [
            'arrivage_index',
            'truck_arrival_index',
            'mvt_traca_index',
            'pack_index',
            'receipt_association_index',
            'en_cours',
            'emergency_index',
        ],
    },
    {
        menu : 'qualite',
        subMenu : [
            'dispute_index',
        ],
    },
    {
        menu : 'demande',
        subMenu : [
            'collecte_index',
            'demande_index',
            'handling_index',
            'dispatch_index',
            'transfer_request_index',
            'purchase_request_index',
            'transport_request_index',
            'shipping_request_index',
        ],
    },
    {
        menu : 'ordre',
        subMenu : [
            'ordre_collecte_index',
            'livraison_index',
            'preparation_index',
            'preparation_planning_index',
            'transfer_order_index',
            'reception_index',
            'transport_order_index',
            'transport_planning_index',
            'transport_round_index',
            'transport_subcontract_index',
        ],
    },
    {
        menu : 'stock',
        subMenu : [
            'article_index',
            'reference_article_index',
            'article_fournisseur_index',
            'mouvement_stock_index',
            'inventory_mission_index',
            'alerte_index',
        ],
    },
    {
        menu : 'referentiel',
        subMenu : [
            'supplier_index',
            'emplacement_index',
            'chauffeur_index',
            'transporteur_index',
            'nature_index',
            'vehicle_index',
            'project_index',
            'customer_index',
        ],
    },
    {
        menu: 'iot',
        subMenu: [
            'sensor_wrapper_index',
            'trigger_action_index',
            'pairing_index',
        ],
    },
]

function isLinkAndIsAvailable($el, queueLink, visitedLinks) {
    return $el.attr('href') !== undefined && !isAlreadyInStack(queueLink,$el) && !visitedLinks.includes($el.attr('href'));
}

function isDivAndIsAvailable($el, visitedLinksText) {
    return $el.attr('href') === undefined && !visitedLinksText.includes($el.text());
}

function isAlreadyInStack(queueLink, $el) {
    return queueLink.includes($el.attr('href'));
}

function visiteLink(link) {
    cy.visit(link)
    cy.log("Currenlty visiting : " + link)
    cy.wait('@request').its('response.statusCode').should('not.eq', 500)
}

function visiteLinkText($el) {
    $el.click()
    cy.wait('@request').its('response.statusCode').should('not.eq', 500)
}

describe('Open all pages', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.intercept('*').as('request');
    })

    it('Pages from menu', () => {
        // todo: ouvrir la modal de création pour chaque page et vérifier que la modal est ouverte est contient au moins 1 input
        menuPages.forEach((menuPage) => {
            menuPage.subMenu.forEach((subMenu) => {
                cy.navigateInNavMenu(menuPage.menu, subMenu)
                // request should not have 500 status code
                cy.wait('@request').its('response.statusCode').should('not.eq', 500)
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
                        visiteLinkText($el);
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
                    cy.get('.modal').should('be.visible');
                })
            })
        })
    })
})
