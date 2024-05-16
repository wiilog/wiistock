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
    {
        menu : 'parametre',
    },
    {
        menu : 'documentation',
    },
    {
        menu : 'phone',
    },
]

describe('Open all pages', () => {
    beforeEach(() => {
        cy.login(user);
        cy.visit('/');
        cy.intercept('*').as('request');
    })

    it('Pages from menu', () => {
        menuPages.forEach((menuPage) => {
            menuPage.subMenu?.forEach((subMenu) => {
                cy.navigateInNavMenu(menuPage.menu, subMenu)
                // request should not have 500 status code
                cy.wait('@request').its('response.statusCode').should('not.eq', 500)
            })
        })
    })

    it('Pages from setting', () => {

    })
})
