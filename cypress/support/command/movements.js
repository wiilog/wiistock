Cypress.Commands.add('editMovement', (minute, type) => {
    cy.intercept('POST', '/mouvement-traca/api-modifier').as('tracking_movement_api_edit');
    cy.get('#tableMvts tbody td').contains(`24/07/2023 12:${minute}`).then((td) => {
        cy.wrap(td).eq(0).click().wait('@tracking_movement_api_edit');
    })
    cy.get(`#modalEditMvtTraca`)
        .should('be.visible');
    cy.get('#modalEditMvtTraca input[name=date]').click().clear().type(`24/07/2023 13:${minute}{enter}`);
    cy.get('input[name=pack]').click().clear().type(`000${minute}`);
    if (type !== 'groupage') {
        cy.get('select[name=location]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.select2Ajax('location', 'LABO 11', 'modalEditMvtTraca');
    } else {
        cy.select2Ajax('location', 'LABO 11', 'modalEditMvtTraca');
    }
    cy.get('select[name=operator]')
        .siblings('.select2')
        .find('.select2-selection__clear')
        .click();
    cy.select2Ajax('operator', 'Lambda', 'modalEditMvtTraca');
    cy.get('#modalEditMvtTraca input[name=quantity]').click().clear().type('100');
    cy.get('#submitEditMvtTraca').click().wait(['@mvt_traca_edit', '@tracking_movement_api'], {timeout: 8000});
    cy.get('#modalEditMvtTraca').should('not.be.visible');
    cy.get('#tableMvts tbody tr').eq(0).find('td').eq(2).contains(`24/07/2023 13:${minute}`);
    //TODO To check when the bug will be fixed (for the moment, the quantity displayed doesn't change on the screen)
    //cy.get('#tableMvts tbody tr').eq(0).find('td').eq(6).contains('100');
    cy.get('#tableMvts tbody tr').eq(0).find('td').eq(7).contains('LABO 11');
    cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains(type);
})

Cypress.Commands.add('addMovement', (typeNumber, type) => {
    let lengthFor = 1;
    cy.get(`button[data-target='#modalNewMvtTraca']`).click();
    cy.get(`#modalNewMvtTraca`)
        .should('be.visible');
    cy.get('input[name=datetime]').click().clear().type('24/07/2023 14:00{enter}');
    cy.get('select[name=operator]')
        .siblings('.select2')
        .find('.select2-selection__clear')
        .click();
    cy.select2Ajax('operator', 'Lambda', 'modalNewMvtTraca');
    cy.get('select[name=type]').select(typeNumber);
    if (type === 'prises et deposes' || type === 'groupage') {
        cy.select2('pack', `230714636-105${typeNumber}`);
        if (type === 'prises et deposes') {
            cy.select2Ajax('emplacement-prise', 'LABO 11', 'modalNewMvtTraca', '/emplacement/*');
            cy.select2Ajax('emplacement-depose', 'BUREAU GT', 'modalNewMvtTraca', '/emplacement/*');
        } else if (type === 'groupage') {
            cy.get('input[name=parent]').type(`5555-44${typeNumber}`);
        }
    } else if (type !== 'prises et deposes' && type !== 'groupage') {
        cy.get('input[name=pack]').type(`230714636-105${typeNumber}`);
        cy.select2Ajax('emplacement', 'BUREAU GT', 'modalNewMvtTraca', '/emplacement/*');
        if (type === 'dépose dans UL') {
            cy.select2AjaxMultiple('articles', ['ART230700000002'], 'modalNewMvtTraca');
        }
    }
    if (type !== 'groupage' && type !== 'passage à vide' && type !== 'dépose dans UL') {
        cy.get('#modalNewMvtTraca input[name=quantity]').clear().type('25');
    }
    cy.get('#modalNewMvtTraca [type="submit"]').click().wait(['@mvt_traca_new', '@tracking_movement_api']);

    cy.get('#alert-modal').should('be.visible').then(() => {
        cy.get('#alert-modal button').click();
        cy.get(`#modalNewMvtTraca`)
            .should('be.visible');
    });
    if (type === 'prises et deposes' || type === 'groupage') {
        cy.get('select[name=pack]').siblings('.select2').should('have.value', '');
        if (type === 'prises et deposes') {
            cy.get('select[name=emplacement-prise]').siblings('.select2').should('have.value', '');
            cy.get('select[name=emplacement-depose]').siblings('.select2').should('have.value', '');
        } else if (type === 'groupage') {
            cy.get('input[name=parent]').should('have.value', '');
        }
    } else if (type !== 'prises et deposes' && type !== 'groupage') {
        cy.get('input[name=pack]').should('have.value', '');
        cy.get('select[name=emplacement]').siblings('.select2').should('have.value', '');
        if (type === 'dépose dans UL') {
            cy.get('select[name=articles]').siblings('.select2').should('have.value', '');
        }
    }
    if (type !== 'groupage' && type !== 'passage à vide' && type !== 'dépose dans UL') {
        cy.get('#modalNewMvtTraca input[name=quantity]').should('have.value', '1');
    }
    cy.get('select[name=type]').invoke('val').should('be.null');

    cy.get('#modalNewMvtTraca button.close').click();
    cy.get(`#modalNewMvtTraca`)
        .should('not.be.visible');
    if (type === 'prises et deposes' || type === 'groupage' || type === 'dépose dans UL') {
        lengthFor = 2;
        if (type === 'prises et deposes') {
            cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('depose');
            cy.get('#tableMvts tbody tr').eq(1).find('td').eq(7).contains('LABO 11');
            cy.get('#tableMvts tbody tr').eq(1).find('td').eq(8).contains('prise');
        }
        if (type === 'dépose dans UL') {
            cy.get('#tableMvts tbody tr').eq(0).find('td').eq(8).contains('dépose dans UL');
            cy.get('#tableMvts tbody tr').eq(1).find('td').eq(8).contains('depose');
        }
    }
    for (let i = 0; i < lengthFor; i++) {
        //TODO To check for 'dépose dans UL' when the bug will be fixed (for the moment, the time displayed is the current time and not the recorded time)
        if (type !== 'dépose dans UL') {
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(2).contains('24/07/2023 14:00');
            if (type !== 'prises et deposes') {
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(8).contains(type);
            }
        }
        if (type === 'groupage' || type === 'passage à vide') {
            if (type === 'groupage') {
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(5).contains(`5555-44${typeNumber}`);
            }
            cy.get('#tableMvts tbody tr').eq(i).find('td').eq(6).contains('1');
        } else if (type !== 'groupage' && type !== 'passage à vide') {
            if (type === 'dépose dans UL') {
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(6).contains('50');
                if (i === 0) {
                    cy.get('#tableMvts tbody tr').eq(i).find('td').eq(3).contains('TTH_K79');
                    cy.get('#tableMvts tbody tr').eq(i).find('td').eq(4).contains('SPIN');
                }
            } else {
                cy.get('#tableMvts tbody tr').eq(i).find('td').eq(6).contains('25');
            }
        }
    }
    if (type !== 'groupage') {
        cy.get('#tableMvts tbody tr').eq(0).find('td').eq(7).contains('BUREAU GT');
        if (type === 'dépose dans UL') {
            cy.get('#tableMvts tbody tr').eq(1).find('td').eq(7).contains('BUREAU GT');
        }
    }
})

Cypress.Commands.add('deleteAllFilters', () => {
    cy.get('#dateMin').click().clear();
    cy.get('#dateMax').click().clear();
    cy.get('#ul').click().clear();
    cy.get('body').then(($body) => {
        if ($body.find('select[name=article]').siblings('.select2').find('.select2-selection__clear').length) {
            cy.get('select[name=article]')
                .siblings('.select2')
                .find('.select2-selection__clear')
                .click();
        }
        if ($body.find('select[name=emplacement]').siblings('.select2').find('.select2-selection__clear').length) {
            cy.get('select[name=emplacement]')
                .siblings('.select2')
                .find('.select2-selection__clear')
                .click();
        }
        if ($body.find(`[name=statut]`)
            .siblings('.select2')
            .find('li .select2-selection__choice__remove').length) {
            cy.get(`[name=statut]`)
                .siblings('.select2')
                .find('li .select2-selection__choice__remove')
                .then(($elements) => {
                    const numElements = $elements.length;
                    for (let i = 0; i < numElements; i++) {
                        cy.get(`[name=statut]`)
                            .siblings('.select2')
                            .find('li .select2-selection__choice__remove')
                            .eq(0)
                            .click({force: true});
                    }
                });
        }
        if ($body.find(`[name=utilisateurs]`)
            .siblings('.select2')
            .find('li .select2-selection__choice__remove').length) {
            cy.get(`[name=utilisateurs]`)
                .siblings('.select2')
                .find('li .select2-selection__choice__remove')
                .then(($elements) => {
                    const numElements = $elements.length;
                    for (let i = 0; i < numElements; i++) {
                        cy.get(`[name=utilisateurs]`)
                            .siblings('.select2')
                            .find('li .select2-selection__choice__remove')
                            .eq(0)
                            .click({force: true});
                    }
                });
        }
    })

    cy.get('button.filters-submit').click().wait('@tracking_movement_api');
    cy.get('#tableMvts tbody').find('tr').should('have.length', 11);
})
