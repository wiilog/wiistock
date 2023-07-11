const linesTableFreeFieldsComponent = 'table[data-table-processing=fixedFields] tbody tr';
const user = {
    email: 'Test@test.fr', password: 'Test123456!',
}

describe('Create and edit logistics unit', () => {

    beforeEach(() => {
        cy.login(user.email, user.password);
        cy.visit('/');
    })

    it('should get the right permissions', () => {
        cy.intercept('POST', '/parametrage/enregistrer').as('settings_save');
        cy.openSettingsItem('arrivages');
        cy.get(`[data-menu=configurations]`).find('input[type=checkbox]')
            .uncheck({force: true});
        cy.get(`[data-menu=configurations] [data-name=REDIRECT_AFTER_NEW_ARRIVAL]`).find('input[type=checkbox]')
            .check({force: true});
        cy.get(`[data-menu=configurations] [data-name=AUTO_PRINT_LU]`).find('input[type=checkbox]')
            .check({force: true});
        cy.get(`[data-menu=configurations] [data-name=SEND_MAIL_AFTER_NEW_ARRIVAL]`).find('input[type=checkbox]')
            .check({force: true});

        cy.get('[data-menu=configurations]').then(($item) => {
            if ($item.find('select[name=MVT_DEPOSE_DESTINATION]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('select[name=MVT_DEPOSE_DESTINATION]').siblings('.select2').find('.select2-selection__clear').click();
            }
            if ($item.find('select[name=DROP_OFF_LOCATION_IF_CUSTOMS]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('select[name=DROP_OFF_LOCATION_IF_CUSTOMS]').siblings('.select2').find('.select2-selection__clear').click();
            }
            if ($item.find('select[name=DROP_OFF_LOCATION_IF_EMERGENCY]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('select[name=DROP_OFF_LOCATION_IF_EMERGENCY]').siblings('.select2').find('.select2-selection__clear').click();
            }
        })

        cy.get('button.save-settings')
            .click();
        cy.get(`[data-menu=champs_fixes]`)
            .eq(0)
            .click();

        cy.get(linesTableFreeFieldsComponent)
            .find('td')
            .should('have.length.gt', 1, {timeout: 10000});
        cy.get(`[data-menu=champs_fixes]`)
            .find('input[type=checkbox]')
            .uncheck({force: true});

        const columnsToCheck = [1, 2, 4, 5];
        cy.get(linesTableFreeFieldsComponent)
            .each((tr) => {
                columnsToCheck.forEach((columnIndex) => {
                    cy.wrap(tr)
                        .find(`td:eq(${columnIndex}) input[type=checkbox]`)
                        .check({force: true});
                });
            });

        cy.get('button.save-settings')
            .click()
            .wait('@settings_save');
        cy.wait(100);
    })

    it("should add incoming logistics unit", () => {
        cy.navigateInNavMenu('traca', 'arrivage_index');
        cy.get('button[name=new-arrival]').click();
        cy.get(`#modalNewArrivage`).should('be.visible');

        cy.select2Ajax('fournisseur', 'ADVANCED');
        cy.select2('transporteur', 'Alexis');
        cy.select2('chauffeur', 'FAURE');
        cy.get(`input[name=noTracking]`).click().type('12345');
        cy.select2('numeroCommandeList', ['1234', '4567']);
        cy.get('#modalNewArrivage select[name=type]').select(1);
        cy.get('#modalNewArrivage select[name=status]').select(1);
        cy.select2Ajax('dropLocation', 'REMIS AU CLIENT');
        cy.select2('destinataire', 'Benjamin');
        cy.select2('acheteurs', ['Amelie', 'Christine']);
        cy.get('#modalNewArrivage select[name=businessUnitbusinessUnit]').select(0);
        cy
            .get(`#modalNewArrivage [name=printArrivage]`).find('input[type=checkbox]')
            .check({force: true});
        cy
            .get(`#modalNewArrivage [name=printPacks]`).find('input[type=checkbox]')
            .check({force: true});

        cy.select2Ajax('project', 'LABO 1');

        // cy.get('#modalNewArrivage button').contains('Enregistrer').click()
        // cy.wait(10000);
    })
})
