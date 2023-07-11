const path = require('path');
const downloadsFolder = Cypress.config('downloadsFolder');
const linesTableFreeFieldsComponent = 'table[data-table-processing=fixedFields] tbody tr';
const numberPacksPalette = 1;
const numberPacksDemiPalette = 0;
const numberPacksBac = 0;
const numberPacksColis = 4;
const LUArrivals = {
    fournisseur: 'ADVANCED',
    transporteur: 'Alexis',
    chauffeur: 'FAURE',
    noTracking: '12345',
    firstNumeroCommandeList: '1234',
    secondNumeroCommandeList: '4567',
    type: 'standard',
    statut: 'conforme',
    dropLocation: 'REMIS AU CLIENT',
    destinataire: 'Benjamin Assie',
    firstAcheteurs: 'Christine Castellana',
    secondAcheteurs: 'Amelie Abdellaoui',
    noProject: '12',
    businessUnit: 'RECYCLAGE',
    project: 'LABO 1',
    comment: 'Cypress',
    file: 'logo.jpg',
    numberPacks: numberPacksPalette + numberPacksDemiPalette + numberPacksBac + numberPacksColis,
}
let logisticUnitsNumber;
let logisticUnitsDate;
const LUArrivalsChanged = {
    fournisseur: 'BIMBA',
    transporteur: 'GT',
    chauffeur: 'HELLO',
    noTracking: '7654',
    firstNumeroCommandeList: '9876',
    secondNumeroCommandeList: '4321',
    statut: 'LITIGE',
    destinataire: 'Audrey Berthelot',
    firstAcheteurs: 'Carine Jahan',
    secondAcheteurs: 'Elsa Parrat',
    noProject: '78',
    businessUnit: 'POMPES',
    comment: 'Modification Cypress',
    file: 'logo.jpg',
}
const newLU = {
    project: 'GAZ DE BORDEAUX',
}
const dispute = {
    type: 'manque BL',
    reporter: 'Alain Persico',
    status: 'En cours de traitement',
    firstLU: '',
    secondLU: '',
}
const disputeChanged = {
    type: 'écart qualité',
    reporter: 'Cecilia Dupre',
    status: 'Réception faite',
    firstLU: '',
    secondLU: '',
}
let disputeCreationDate;
let logisticUnitsCreationDate;
const user = {
    email: 'Test@test.fr',
    password: 'Test123456!',
}

describe('Create and edit logistic units arrivals', () => {

    beforeEach(() => {
        const downloadsFolder = Cypress.config('downloadsFolder');
        cy.exec(`del /q ${downloadsFolder}\\*`, {failOnNonZeroExit: false});
        cy.login(user.email, user.password);
        cy.visit('/');
    })

    it('should get the right permissions', () => {
        cy.intercept('POST', '/parametrage/enregistrer').as('settings_save');
        cy.intercept('GET', '/parametrage/champs-libres/api/*').as('settings_free_field_api');

        cy.openSettingsItem('arrivages');
        cy.get(`[data-menu=configurations] input[type=checkbox]`)
            .uncheck({force: true});
        cy.get(`[data-menu=configurations] [data-name=AUTO_PRINT_LU] input[type=checkbox]`)
            .check({force: true});
        cy.get(`[data-menu=configurations] [data-name=SEND_MAIL_AFTER_NEW_ARRIVAL] input[type=checkbox]`)
            .check({force: true});

        cy.get('[data-menu=configurations]').then(($item) => {
            if ($item.find('select[name=MVT_DEPOSE_DESTINATION]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('select[name=MVT_DEPOSE_DESTINATION]')
                    .siblings('.select2')
                    .find('.select2-selection__clear')
                    .click();
            }
            if ($item.find('select[name=DROP_OFF_LOCATION_IF_CUSTOMS]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('select[name=DROP_OFF_LOCATION_IF_CUSTOMS]')
                    .siblings('.select2')
                    .find('.select2-selection__clear')
                    .click();
            }
            if ($item.find('select[name=DROP_OFF_LOCATION_IF_EMERGENCY]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('select[name=DROP_OFF_LOCATION_IF_EMERGENCY]')
                    .siblings('.select2')
                    .find('.select2-selection__clear')
                    .click();
            }
        })

        cy.get('button.save-settings')
            .click();
        cy.get(`[data-menu=champs_fixes]`)
            .eq(0)
            .click();

        cy.get(linesTableFreeFieldsComponent)
            .find('td', {timeout: 10000})
            .should('have.length.gt', 1);
        cy.get(`[data-menu=champs_fixes] input[type=checkbox]`)
            .uncheck({force: true});

        const columnsToCheck = [1, 2, 4, 5];
        cy.get(linesTableFreeFieldsComponent).each((tr) => {
            columnsToCheck.forEach((columnIndex) => {
                cy
                    .wrap(tr).find(`td:eq(${columnIndex}) input[type=checkbox]`)
                    .check({force: true});
            });
        });

        cy.get('button.save-settings')
            .click().wait('@settings_save');
        cy.wait(200);
    })

    it("should add a new logistic units arrivals without the redirect after a new arrival", () => {
        cy.intercept('GET', '/arrivage/*/etiquettes?printPacks=1&printArrivage=1&template=1').as('print_arrivage_bar_codes_template_1');
        cy.intercept('GET', '/arrivage/*/etiquettes?printPacks=1&printArrivage=1&template=4').as('print_arrivage_bar_codes_template_4')
        cy.intercept('GET', '/arrivage/*/etiquettes?printPacks=1&printArrivage=1&template=6').as('print_arrivage_bar_codes_template_6');
        cy.intercept('GET', '/arrivage/*/etiquettes?printPacks=1&printArrivage=1&template=7').as('print_arrivage_bar_codes_template_7');
        cy.intercept('POST', '/arrivage/creer').as('arrivage_new');

        cy.navigateInNavMenu('traca', 'arrivage_index');
        cy.get('button[name=new-arrival]')
            .click();
        cy.get(`#modalNewArrivage`)
            .should('be.visible');

        cy.select2Ajax('fournisseur', LUArrivals.fournisseur);
        cy.select2('transporteur', LUArrivals.transporteur);
        cy.select2('chauffeur', LUArrivals.chauffeur);
        cy.get(`input[name=noTracking]`)
            .click()
            .type(LUArrivals.noTracking);
        cy.select2('numeroCommandeList', [LUArrivals.firstNumeroCommandeList, LUArrivals.secondNumeroCommandeList]);
        cy.get('#modalNewArrivage select[name=type]')
            .select(LUArrivals.type);
        cy.get('#modalNewArrivage select[name=status]')
            .select(LUArrivals.statut);
        cy.select2Ajax('dropLocation', LUArrivals.dropLocation, '/emplacement/autocomplete*');
        cy.select2('destinataire', LUArrivals.destinataire);
        cy.select2('acheteurs', [LUArrivals.firstAcheteurs, LUArrivals.secondAcheteurs]);
        cy.get(`input[name=noProject]`)
            .click()
            .type(LUArrivals.noProject);
        cy.get('#modalNewArrivage select[name=businessUnit]')
            .select(LUArrivals.businessUnit);
        cy.get(`#modalNewArrivage`)
            .find('input[name=customs]')
            .uncheck({force: true});
        cy.get(`#modalNewArrivage`)
            .find('input[name=frozen]')
            .uncheck({force: true});
        cy.get(`#modalNewArrivage`)
            .find('input[name=printArrivage]')
            .check({force: true});
        cy.get(`#modalNewArrivage`)
            .find('input[name=printPacks]')
            .check({force: true});

        cy.get('input[name=packs]').eq(0)
            .click()
            .clear()
            .type(`${numberPacksPalette}`);
        cy.get('input[name=packs]').eq(1)
            .click()
            .clear()
            .type(`${numberPacksDemiPalette}`);
        cy.get('input[name=packs]').eq(2)
            .click()
            .clear()
            .type(`${numberPacksBac}`);
        cy.get('input[name=packs]').eq(3)
            .click()
            .clear()
            .type(`${numberPacksColis}`);

        cy.select2Ajax('project', LUArrivals.project);
        cy.get('.ql-editor')
            .click()
            .type(LUArrivals.comment);
        cy.get('input[type=file]')
            .selectFile(`cypress/fixtures/${LUArrivals.file}`, {force: true});

        cy.getTheDate().then(logisticUnitsArrivalCreationDate => {
            cy.wrap(logisticUnitsArrivalCreationDate).as('logisticUnitsArrivalCreationDate');
        })

        cy.get('button[type=submit]')
            .click()
            .wait('@arrivage_new', {timeout: 30000});

        cy.get('#alert-modal', {timeout: 30000})
            .should('be.visible');

        cy.wait(['@print_arrivage_bar_codes_template_1', '@print_arrivage_bar_codes_template_4'], {timeout: 150000})
            .then((intercepts) => {
                intercepts.forEach((intercept) => {
                    const filename = intercept.response.headers['content-disposition'].split('filename=')[1];
                    const downloadedFilename = path.join(downloadsFolder, filename);
                    cy.readFile(downloadedFilename).should('exist');
                })
            })

        cy.get('#modalNewArrivage button.close')
            .click();
        cy.get(`#modalNewArrivage`)
            .should('not.be.visible');

        cy.get('@logisticUnitsArrivalCreationDate').then((logisticUnitsArrivalCreationDateValue) => {
            cy.get('#arrivalsTable tbody td')
                .eq(2)
                .contains(logisticUnitsArrivalCreationDateValue)
                .then(($td) => {
                    logisticUnitsDate = $td.text();
                    const logisticUnitsDateTimeSplit = logisticUnitsDate.split(' ')
                    const logisticUnitsDateSplit = logisticUnitsDateTimeSplit[0].split('/');
                    const logisticUnitsTimeSplit = logisticUnitsDateTimeSplit[1].split(':');
                    logisticUnitsNumber = logisticUnitsDateSplit[2].slice(-2) + logisticUnitsDateSplit[1] + logisticUnitsDateSplit[0] + logisticUnitsTimeSplit[0] + logisticUnitsTimeSplit[1] + logisticUnitsTimeSplit[2] + '-01';
                    cy.get('#arrivalsTable tbody td')
                        .eq(3)
                        .contains(logisticUnitsNumber);
                })
        })

        cy.get('#arrivalsTable tbody td')
            .eq(4)
            .contains(LUArrivals.transporteur);
        cy.get('#arrivalsTable tbody td')
            .eq(5)
            .contains(LUArrivals.type);
        cy.get('#arrivalsTable tbody td')
            .eq(6)
            .contains(LUArrivals.fournisseur);

        cy.get('#arrivalsTable tbody td')
            .eq(7)
            .contains(LUArrivals.numberPacks, {timeout: 30000});

        cy.get('#arrivalsTable tbody td')
            .eq(8)
            .contains(LUArrivals.statut, {timeout: 30000});
    })


    it("should check the new logistic units arrivals created", () => {
        cy.intercept('POST', 'arrivage/packs/api/*').as('packs_api');

        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        // cy.get('table#arrivalsTable tbody tr').first().find('td').eq(3).then(($text) => {
        //     let logisticUnitsNumber = $text.text();
        //     cy.wrap(logisticUnitsNumber).as('logisticUnitsNumber');
        // })
        // cy.get('table#arrivalsTable tbody tr').first().click();
        cy.get('#arrivalsTable tbody td').eq(3).each(($td) => {
            cy.wrap($td)
                .invoke('text')
                .then((text) => {
                    if (text.trim() === logisticUnitsNumber) {
                        cy.wrap($td).click();
                    }
                });
        });
        // TODO !!title!!
        cy.get('[title=Type]')
            .parent()
            .siblings()
            .contains(LUArrivals.type);
        cy.get('[title=Statut]')
            .parent()
            .siblings()
            .contains(LUArrivals.statut, {matchCase: false});
        cy.get('[title=Fournisseur]')
            .parent()
            .siblings()
            .contains(LUArrivals.fournisseur);
        cy.get("[title='Emplacement de dépose']")
            .parent()
            .siblings()
            .contains(LUArrivals.dropLocation);
        cy.get('[title=Transporteur]')
            .parent()
            .siblings()
            .contains(LUArrivals.transporteur);
        cy.get('[title=Chauffeur]')
            .parent()
            .siblings()
            .contains(LUArrivals.chauffeur);
        cy.get("[title='N° tracking transporteur']")
            .parent()
            .siblings()
            .contains(LUArrivals.noTracking);
        cy.get("[title='N° commande / BL']")
            .parent()
            .siblings()
            .should('contain', LUArrivals.firstNumeroCommandeList)
            .and('contain', LUArrivals.secondNumeroCommandeList);
        cy.get('[title=Destinataire]')
            .parent()
            .siblings()
            .contains(LUArrivals.destinataire);
        cy.get("[title='Acheteur(s)']")
            .parent()
            .siblings()
            .should('contain', LUArrivals.firstAcheteurs)
            .and('contain', LUArrivals.secondAcheteurs);
        cy.get("[title='Numéro de projet']")
            .parent()
            .siblings()
            .contains(LUArrivals.noProject);
        cy.get("[title='Business unit']")
            .parent()
            .siblings()
            .contains(LUArrivals.businessUnit);
        cy.get('[title=Douane]')
            .parent()
            .siblings()
            .contains('Non');
        cy.get('[title=Congelé]')
            .parent()
            .siblings()
            .contains('Non');
        cy.get('[title=Commentaire]')
            .parent()
            .siblings()
            .contains(LUArrivals.comment);
        cy.get('a[download]')
            .contains(LUArrivals.file);

        cy.wait('@packs_api', {timeout: 20000});

        cy.get('#tablePacks tbody tr')
            .should('have.length', LUArrivals.numberPacks);

        // A remettre pour les tests individuels
        // cy.get('@logisticUnitsNumber').then((logisticUnitsNumber) => {
        //     cy.get('table#tablePacks tbody tr').first().find('td').eq(2).contains(logisticUnitsNumber);
        //     const date = new Date();
        //     const year = date.getFullYear();
        //     cy.get('table#tablePacks tbody tr').first().find('td').eq(4).contains(`${logisticUnitsNumber.slice(4, 6)}/${logisticUnitsNumber.slice(2, 4)}/${year.toString().slice(0, 2)}${logisticUnitsNumber.slice(0, 2)} ${logisticUnitsNumber.slice(6, 8)}:${logisticUnitsNumber.slice(8, 10)}`);
        // })

        let numberPalette = 0;
        let numberDemiPalette = 0;
        let numberBac = 0;
        let numberColis = 0;

        cy.get('#tablePacks tbody tr').each(($line) => {
            const text = $line.find('td').eq(1).text().trim();

            if (text === 'PALETTE') {
                numberPalette++;
            } else if (text === 'DEMI PALETTE') {
                numberDemiPalette++;
            } else if (text === 'BAC') {
                numberBac++;
            } else if (text === 'COLIS') {
                numberColis++;
            }

        }).then(() => {
            expect(numberPalette).to.equal(numberPacksPalette);
            expect(numberDemiPalette).to.equal(numberPacksDemiPalette);
            expect(numberBac).to.equal(numberPacksBac);
            expect(numberColis).to.equal(numberPacksColis);
        });

        cy.get('#tablePacks tbody tr').each(($line) => {
            cy.wrap($line).find('td').eq(2)
                .contains(logisticUnitsNumber);
        });
        cy.get('#tablePacks tbody tr').each(($line) => {
            let logisticUnitsDateRegex = new RegExp(`${logisticUnitsDate.slice(0, 15)}(${logisticUnitsDate[15]}|${logisticUnitsDate[15] + 1})`);
            cy.wrap($line).find('td').eq(4)
                .contains(logisticUnitsDateRegex);
        });

        cy.get('#tablePacks tbody tr').each(($line) => {
            cy.wrap($line).find('td').eq(3)
                .contains(LUArrivals.project);
        });
        cy.get('#tablePacks tbody tr').each(($line) => {
            cy.wrap($line).find('td').eq(5)
                .contains(LUArrivals.dropLocation);
        });
    })

    it("should add a new dispute", () => {
        cy.intercept('POST', 'arrivage/packs/api/*').as('packs_api');
        cy.intercept('GET', '/arrivage/new-dispute-template*').as('new_dispute_template');
        cy.intercept('POST', '/arrivage/creer-litige*').as('dispute_new');

        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        // cy.get('table#arrivalsTable tbody tr').first().click();
        cy.get('table#arrivalsTable tbody tr td').eq(3).each(($td) => {
            cy.wrap($td).invoke('text').then((text) => {
                if (text.trim() === logisticUnitsNumber)
                    cy.wrap($td).click();
            });
        });

        cy.wait('@packs_api')

        cy.get('#tablePacks tbody tr').first().find('td').eq(2).then(($text) => {
            dispute.firstLU = $text.text();
            cy.wrap(dispute.firstLU).as('firstLU');
        })
        cy.get('#tablePacks tbody tr').eq(2).find('td').eq(2).then(($text) => {
            dispute.secondLU = $text.text();
            cy.wrap(dispute.secondLU).as('secondLU');
        })

        cy.get('button.new-dispute-modal')
            .click().wait('@new_dispute_template', {timeout: 10000});
        cy.get(`#modalNewLitige`, {timeout: 10000})
            .should('be.visible');
        cy.get('#modalNewLitige [name=disputeType]')
            .select(dispute.type);
        cy.get('#modalNewLitige').then(($modal) => {
            if ($modal.find('[name=disputeReporter]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('[name=disputeReporter]').siblings('.select2').find('.select2-selection__clear')
                    .click();
                cy.get(`[name=disputeReporter]`).siblings('.select2')
                    .click();
            }
        })
        cy.select2Ajax('disputeReporter', dispute.reporter);
        cy.get('#modalNewLitige [name=disputeStatus]')
            .select(dispute.status);
        cy.get('@firstLU').then((firstLU) => {
            cy.get('@secondLU').then((secondLU) => {
                cy.select2('disputePacks', [firstLU, secondLU]);
            })
        })

        cy.getTheDate().then(value => {
            cy.wrap(value).as('disputeCreationDate');
        })

        cy.get('#submitNewLitige')
            .click().wait('@dispute_new');

        cy.get('#tableArrivageLitiges tbody tr')
            .should('have.length', 1);
        cy.get('@disputeCreationDate').then((disputeCreationDateValue) => {
            disputeCreationDate = disputeCreationDateValue;
            cy.get('#tableArrivageLitiges_wrapper tbody tr')
                .first().find('td').eq(1)
                .contains(disputeCreationDate);
        })
        cy.get('#tableArrivageLitiges_wrapper tbody tr').first().find('td').eq(2)
            .contains(dispute.status);
        cy.get('#tableArrivageLitiges_wrapper tbody tr').first().find('td').eq(3)
            .contains(dispute.type);
    })

    it("should edit the new dispute created", () => {
        cy.intercept('POST', '/arrivage/litiges/api/*').as('arrivageLitiges_api');
        cy.intercept('POST', '/arrivage/api-modifier-litige').as('litige_api_edit')
        cy.intercept('POST', '/arrivage/modifier-litige*').as('litige_edit_arrivage')

        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        // cy.get('table#arrivalsTable tbody tr').first().click();
        cy.get('table#arrivalsTable tbody tr td').eq(3).each(($td) => {
            cy.wrap($td).invoke('text').then((text) => {
                if (text.trim() === logisticUnitsNumber) {
                    cy.wrap($td).click();
                }
            });
        });

        cy.wait('@arrivageLitiges_api')

        cy.get('#tablePacks tbody tr').last().find('td').eq(2).then(($text) => {
            disputeChanged.firstLU = $text.text();
            cy.wrap(disputeChanged.firstLU).as('firstLU');
        })
        cy.get('#tablePacks tbody tr').eq(3).find('td').eq(2).then(($text) => {
            disputeChanged.secondLU = $text.text();
            cy.wrap(disputeChanged.secondLU).as('secondLU');
        })

        cy.get('#tableArrivageLitiges tbody tr').first()
            .click().wait('@litige_api_edit', {timeout: 8000});

        cy.get('#modalEditLitige [name=disputeType]')
            .contains(dispute.type);
        cy.get('#modalEditLitige [name=disputeStatus]')
            .contains(dispute.status);
        cy.get('#modalEditLitige [name=disputeReporter]')
            .contains(dispute.reporter);
        cy.get(`#modalEditLitige [name=pack]`)
            .should('contain', dispute.firstLU)
            .and('contain', dispute.secondLU);

        cy.get('#modalEditLitige [name=disputeType]')
            .select(disputeChanged.type);
        cy.get('#modalEditLitige [name=disputeStatus]')
            .select(disputeChanged.status);
        cy.get('#modalEditLitige [name=disputeReporter]')
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.get(`#modalEditLitige [name=disputeReporter]`)
            .siblings('.select2')
            .click();
        cy.select2Ajax('disputeReporter', disputeChanged.reporter);

        cy.get('#modalEditLitige').then(($modal) => {
            if ($modal.find(`[name=pack]`)
                .siblings('.select2')
                .find('li .select2-selection__choice__remove').length) {
                cy.get(`[name=pack]`)
                    .siblings('.select2')
                    .find('li .select2-selection__choice__remove')
                    .then(($elements) => {
                        const numElements = $elements.length;
                        for (let i = 0; i < numElements; i++) {
                            cy.get(`[name=pack]`)
                                .siblings('.select2')
                                .find('li .select2-selection__choice__remove')
                                .eq(0)
                                .click({force: true});
                        }
                    });
            }
        })
        cy.get('@firstLU').then((firstLU) => {
            cy.get('@secondLU').then((secondLU) => {
                cy.select2('pack', [firstLU, secondLU]);
            })
        })

        cy.getTheDate().then(value => {
            cy.wrap(value).as('disputeChangedCreationDate');
        })

        cy.get('#submitEditLitige')
            .click()
            .wait('@litige_edit_arrivage', {timeout: 8000});

        cy.get('#tableArrivageLitiges_wrapper tbody tr')
            .first()
            .find('td')
            .eq(1)
            .contains(disputeCreationDate);
        cy.get('#tableArrivageLitiges_wrapper tbody tr')
            .first()
            .find('td')
            .eq(2)
            .contains(disputeChanged.status);
        cy.get('#tableArrivageLitiges_wrapper tbody tr')
            .first()
            .find('td')
            .eq(3)
            .contains(disputeChanged.type);
        cy.get('@disputeChangedCreationDate').then((disputeChangedCreationDate) => {
            cy.get('#tableArrivageLitiges_wrapper tbody tr')
                .first().find('td').eq(4)
                .contains(disputeChangedCreationDate);
        })
    })

    it("should add a new logistic units", () => {
        cy.intercept('POST', '/arrivage/packs/api/*').as('packs_api');
        cy.intercept('POST', '/arrivage/ajouter-UL').as('arrivage_add_pack');
        cy.intercept('GET', '/arrivage/*/etiquettes?packs%5B%5D=*').as('printPacks')

        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        cy.get('table#arrivalsTable tbody tr').first().click();
        // cy.get('table#arrivalsTable tbody tr td').eq(3).each(($td) => {
        //     cy.wrap($td).invoke('text').then((text) => {
        //         if (text.trim() === logisticUnitsNumber) {
        //             cy.wrap($td).click();
        //         }
        //     });
        // });

        cy.get("[data-target='#modalAddPacks']")
            .click()
            .wait('@packs_api', {timeout: 20000});
        cy.get('#modalAddPacks [name=project]')
            .select(newLU.project);
        cy.get('input[name=pack]').eq(0)
            .clear()
            .type('0');
        cy.get('input[name=pack]').eq(1)
            .clear()
            .type('0');
        cy.get('input[name=pack]').eq(2)
            .clear()
            .type('1');
        cy.get('input[name=pack]').eq(3)
            .clear()
            .type('0');

        // This piece of code inserted before the action triggering the download allows avoids the page load timeout (Cypress problem when we click on the link to download the file)
        cy.window().then(win => {
            const triggerAutIframeLoad = () => {
                const AUT_IFRAME_SELECTOR = '.aut-iframe';

                // get the application iframe
                const autIframe = win.parent.document.querySelector(AUT_IFRAME_SELECTOR);

                if (!autIframe) {
                    throw new ReferenceError(`Failed to get the application frame using the selector '${AUT_IFRAME_SELECTOR}'`);
                }

                autIframe.dispatchEvent(new Event('load'));
                // remove the event listener to prevent it from firing the load event before each next unload (basically before each successive test)
                win.removeEventListener('beforeunload', triggerAutIframeLoad);
            };

            win.addEventListener('beforeunload', triggerAutIframeLoad);
        });

        cy.getTheDate().then(value => {
            logisticUnitsCreationDate = value;
        })

        cy.get('#submitAddPacks').click().wait('@arrivage_add_pack');

        // This piece of code allows to detect downloaded attachment
        cy.wait('@printPacks', {timeout: 100000}).then((res) => {
            const filename = res.response.headers['content-disposition'].split('filename=')[1];
            const downloadedFilename = path.join(downloadsFolder, filename);
            cy.readFile(downloadedFilename).should('exist');
        });

    })

    it("should check the new logistic units", () => {
        cy.intercept('POST', '/arrivage/packs/api/*').as('packs_api');

        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        // cy.get('table#arrivalsTable tbody tr').first().find('td').eq(3).then(($text) => {
        //     let logisticUnitsNumber = $text.text();
        //     cy.wrap(logisticUnitsNumber).as('logisticUnitsNumber');
        // })
        // cy.get('table#arrivalsTable tbody tr').first().click().wait('@packs_api', {timeout: 12000});
        cy.get('#arrivalsTable tbody td').eq(3).each(($td) => {
            cy.wrap($td).invoke('text').then((text) => {
                if (text.trim() === logisticUnitsNumber) {
                    cy.wrap($td).click().wait('@packs_api', {timeout: 12000});
                }
            });
        });

        cy.get('#tablePacks_wrapper thead th')
            .eq(4)
            .dblclick();
        cy.get('#tablePacks tbody tr')
            .first()
            .find('td')
            .eq(1)
            .contains('BAC');

        cy.get('#tablePacks tbody tr')
            .first()
            .find('td')
            .eq(2)
            .contains(logisticUnitsNumber)

        cy.get('#tablePacks tbody tr')
            .first()
            .find('td')
            .eq(3)
            .contains(newLU.project);

        cy.get('#tablePacks tbody tr')
            .first()
            .find('td')
            .eq(4)
            .contains(logisticUnitsCreationDate);
        cy.get('#tablePacks tbody tr')
            .first()
            .find('td')
            .eq(5)
            .contains(LUArrivals.dropLocation);
    })

    it("should edit the new logistic units arrivals created", () => {
        cy.intercept('POST', '/arrivage/api-modifier').as('arrivage_edit_api');
        cy.intercept('POST', '/arrivage/modifier').as('arrivage_edit');

        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        // cy.get('table#arrivalsTable tbody tr').first().click();
        cy.get('table#arrivalsTable tbody tr td').eq(3).each(($td) => {
            cy.wrap($td).invoke('text').then((text) => {
                if (text.trim() === logisticUnitsNumber) {
                    cy.wrap($td).click();
                }
            });
        });

        cy.get('button.split-button')
            .click()
            .wait('@arrivage_edit_api');

        cy.select2Ajax('fournisseur', LUArrivalsChanged.fournisseur, '', false);
        cy.select2Ajax('transporteur', LUArrivalsChanged.transporteur, '', false);
        cy.select2Ajax('chauffeur', LUArrivalsChanged.chauffeur, '', false);
        cy.get(`input[name=noTracking]`)
            .click()
            .clear()
            .type(LUArrivalsChanged.noTracking);

        cy.get('#modalEditArrivage').then(($modal) => {
            if ($modal.find(`[name=numeroCommandeList]`).siblings('.select2')
                .find('li .select2-selection__choice__remove').length) {
                cy.get(`[name=numeroCommandeList]`)
                    .siblings('.select2')
                    .find('li .select2-selection__choice__remove')
                    .then(($elements) => {
                        const numElements = $elements.length;
                        for (let i = 0; i < numElements; i++) {
                            cy.get(`[name=numeroCommandeList]`)
                                .siblings('.select2')
                                .find('li .select2-selection__choice__remove')
                                .eq(0)
                                .click({force: true});
                        }
                    });
            }
        })

        cy.select2('numeroCommandeList', [LUArrivalsChanged.firstNumeroCommandeList, LUArrivalsChanged.secondNumeroCommandeList]);
        cy.get('#modalEditArrivage select[name=statut]')
            .select(LUArrivalsChanged.statut, {force: true});
        cy.select2Ajax('destinataire', LUArrivalsChanged.destinataire);

        cy.get('#modalEditArrivage').then(($modal) => {
            if ($modal.find(`[name=acheteurs]`).siblings('.select2')
                .find('li .select2-selection__choice__remove').length) {
                cy.get(`[name=acheteurs]`)
                    .siblings('.select2')
                    .find('li .select2-selection__choice__remove')
                    .then(($elements) => {
                        const numElements = $elements.length;
                        for (let i = 0; i < numElements; i++) {
                            cy.get(`[name=acheteurs]`)
                                .siblings('.select2')
                                .find('li .select2-selection__choice__remove')
                                .eq(0)
                                .click({force: true});
                        }
                    });
            }
        })
        cy.select2('acheteurs', [LUArrivalsChanged.firstAcheteurs, LUArrivalsChanged.secondAcheteurs]);
        cy.get(`input[name=noProject]`)
            .click()
            .clear()
            .type(LUArrivalsChanged.noProject);
        cy.get('[name=businessUnit]')
            .select(LUArrivalsChanged.businessUnit, {force: true});
        cy.get('.ql-editor')
            .click()
            .clear()
            .type(LUArrivalsChanged.comment);

        cy.get('#modalEditArrivage').then(($modal) => {
            if ($modal.find(`a[data-original-title="${LUArrivals.file}"]`).siblings('svg').length) {
                cy.get(`a[data-original-title="${LUArrivals.file}"]`).siblings('svg').then(($elements) => {
                    const numElements = $elements.length;
                    for (let i = 0; i < numElements; i++) {
                        cy.get(`a[data-original-title="${LUArrivals.file}"]`).siblings('svg')
                            .eq(0)
                            .click();
                    }
                })
            }
        })
        cy.get('input[type=file]')
            .selectFile(`cypress/fixtures/${LUArrivalsChanged.file}`, {force: true});

        cy.get('#submitEditArrivage')
            .click()
            .wait('@arrivage_edit');
    })

    it("should check the modification made on the logistic units arrivals created", () => {
        cy.intercept('POST', 'arrivage/packs/api/*').as('packs_api');
        cy.navigateInNavMenu('traca', 'arrivage_index');

        // A remettre pour les tests individuels
        // cy.get('table#arrivalsTable tbody tr').first().click();
        cy.get('#arrivalsTable tbody td').eq(3).each(($td) => {
            cy.wrap($td).invoke('text').then((text) => {
                if (text.trim() === logisticUnitsNumber) {
                    cy.wrap($td).click();
                }
            });
        });

        cy.get('[title=Statut]')
            .parent().siblings()
            .contains(LUArrivalsChanged.statut, {matchCase: false});
        cy.get('[title=Fournisseur]')
            .parent().siblings()
            .contains(LUArrivalsChanged.fournisseur);
        cy.get('[title=Transporteur]')
            .parent().siblings()
            .contains(LUArrivalsChanged.transporteur);
        cy.get('[title=Chauffeur]')
            .parent().siblings()
            .contains(LUArrivalsChanged.chauffeur);
        cy.get("[title='N° tracking transporteur']")
            .parent().siblings()
            .contains(LUArrivalsChanged.noTracking);
        cy.get("[title='N° commande / BL']")
            .parent().siblings()
            .should('contain', LUArrivalsChanged.firstNumeroCommandeList)
            .and('contain', LUArrivalsChanged.secondNumeroCommandeList);
        cy.get('[title=Destinataire]')
            .parent().siblings()
            .contains(LUArrivalsChanged.destinataire);
        cy.get("[title='Acheteur(s)']")
            .parent().siblings()
            .should('contain', LUArrivalsChanged.firstAcheteurs)
            .and('contain', LUArrivalsChanged.secondAcheteurs);
        cy.get("[title='Numéro de projet']")
            .parent().siblings()
            .contains(LUArrivalsChanged.noProject);
        cy.get("[title='Business unit']")
            .parent().siblings()
            .contains(LUArrivalsChanged.businessUnit);
        cy.get('[title=Douane]')
            .parent().siblings()
            .contains('Non');
        cy.get('[title=Congelé]')
            .parent().siblings()
            .contains('Non');
        cy.get('[title=Commentaire]')
            .parent().siblings()
            .contains(LUArrivalsChanged.comment);
        cy.get('a[download]')
            .contains(LUArrivalsChanged.file);
    })
})
