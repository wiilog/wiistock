import {capitalizeFirstLetter, getColumnIndexByColumnName} from "../support/utils";
import routes, {interceptRoute} from "../support/utils/routes";
import {uncaughtException} from "../support/utils";

const user = Cypress.config('user');

const linesTableFreeFieldsComponent = 'table[data-table-processing=fixedFields] tbody tr';

const numberPacksNature1 = 1;
const numberPacksNature2 = 4;

const LUArrivals = {
    fournisseur: 'FOURNISSEUR',
    transporteur: 'DHL',
    chauffeur: 'Georges',
    noTracking: '12345',
    firstNumeroCommandeList: '1234',
    secondNumeroCommandeList: '4567',
    type: 'standard',
    statut: 'Arrivage à traiter',
    dropLocation: 'BUREAU GT',
    destinataire: 'Admin',
    firstAcheteurs: 'Admin',
    secondAcheteurs: 'Lambda',
    noProject: '12',
    businessUnit: 'RECYCLAGE',
    project: 'PROJET',
    comment: 'Cypress',
    file: 'logo.jpg',
    numberPacks: numberPacksNature1 + numberPacksNature2,
}

const LUArrivalsChanged = {
    fournisseur: 'SAMSUNG',
    transporteur: 'GT',
    chauffeur: 'Georges',
    noTracking: '7654',
    firstNumeroCommandeList: '9876',
    secondNumeroCommandeList: '4321',
    statut: 'ARRIVAGE TRAITÉ',
    destinataire: 'Lambda',
    firstAcheteurs: 'Admin',
    secondAcheteurs: 'Lambda',
    noProject: '78',
    businessUnit: 'POMPES',
    comment: 'Modification Cypress',
    file: 'logo.jpg',
}

const newLU = {
    project: 'PROJET',
}

const dispute = {
    type: 'manque BL',
    reporter: 'Lambda',
    status: 'En cours de traitement',
}

const disputeChanged = {
    type: 'écart qualité',
    reporter: 'Admin',
    status: 'Traité',
}

describe('Setup the environment', () => {
    it('Reset the db', () => {
        cy.startingCypressEnvironnement('$FTP_HOST')
        uncaughtException();
    });
})

describe('Get the right permissions for logistic units arrivals', () => {
    beforeEach(() => {
        interceptRoute(routes.settings_save);
        interceptRoute(routes.settings_free_field_api);

        cy.login(user);
        cy.visit('/');
        cy.openSettingsItem('arrivages');
    })

    it('should get the right permissions', () => {
        cy.get(`[data-menu=configurations] input[type=checkbox]`)
            .uncheck({force: true});
        cy.get(`[data-menu=configurations] [data-name=AUTO_PRINT_LU] input[type=checkbox]`)
            .check({force: true});
        cy.get(`[data-menu=configurations] [data-name=SEND_MAIL_AFTER_NEW_ARRIVAL] input[type=checkbox]`)
            .check({force: true});

        cy.get('[data-menu=configurations]').then(($item) => {
            const selects = [
                {
                    name: 'MVT_DEPOSE_DESTINATION',
                },
                {
                    name: 'DROP_OFF_LOCATION_IF_CUSTOMS',
                },
                {
                    name: 'DROP_OFF_LOCATION_IF_EMERGENCY',
                }
            ]

            selects.forEach((select) => {
                if ($item.find(`select[name=${select.name}]`).siblings('.select2').find('.select2-selection__clear').length) {
                    cy.get(`select[name=${select.name}]`)
                        .siblings('.select2')
                        .find('.select2-selection__clear')
                        .click();
                }
            })
        })

        cy.get('button.save-settings')
            .click();
        cy.get(`[data-menu=champs_fixes]`)
            .eq(0)
            .first()
            .click();

        // check the table has at least one line
        cy.get(linesTableFreeFieldsComponent)
            .find('td', {timeout: 10000})
            .should('have.length.gt', 1);
        // uncheck all the checkboxes
        cy.get(`[data-menu=champs_fixes] input[type=checkbox]`)
            .uncheck({force: true});


        const columnsToCheck = [];
        const columnsToCheckName = ["Afficher","Obligatoire"];

        // get the index of the columns with her name
        cy.get("#table-arrival-fixed-fields thead tr[role=row] th").then(($ths) => {
            $ths.each((index, th) => {
                if (columnsToCheckName.includes(th.textContent)) {
                    // get the index of the columns to check in the datatable
                    // -4 because the first 4 columns come from ??
                    columnsToCheck.push(index - 4);
                }
            });
        })

        cy.get(linesTableFreeFieldsComponent).each((tr) => {
            columnsToCheck.forEach((columnIndex) => {
                cy.wrap(tr).find(`td:eq(${columnIndex}) input[type=checkbox]`)
                    .check({force: true});
            });
        });

        cy.get('button.save-settings')
            .click().wait('@settings_save');
    })
})

describe('Add and edit logistic units arrivals', () => {

    beforeEach(() => {
        interceptRoute(routes.pack_api);
        interceptRoute(routes.print_arrivage_bar_codes_nature_1);
        interceptRoute(routes.print_arrivage_bar_codes_nature_2);
        interceptRoute(routes.arrivage_new);
        interceptRoute(routes.new_dispute_template);
        interceptRoute(routes.dispute_new);
        interceptRoute(routes.arrivage_litiges_api);
        interceptRoute(routes.litige_api_edit);
        interceptRoute(routes.litige_edit_arrivage);
        interceptRoute(routes.arrivage_add_pack);
        interceptRoute(routes.printPacks);
        interceptRoute(routes.arrivage_edit_api);
        interceptRoute(routes.arrivage_edit);

        const downloadsFolder = Cypress.config('downloadsFolder');
        cy.exec(`del /q ${downloadsFolder}\\*`, {failOnNonZeroExit: false});

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'arrivage_index');
    })

        it("should add a new logistic units arrivals without the redirect after a new arrival", () => {

            const selectorModal = '#modalNewArrivage';

            // click btn new Arrival and verify the modal is visible
            cy.openModal(selectorModal, 'noProject', 'button[name=new-arrival]')

            //select2ajax
            cy.select2Ajax('fournisseur', LUArrivals.fournisseur, "modalNewArrivage");

            cy.select2Ajax('dropLocation', LUArrivals.dropLocation, "modalNewArrivage");
            cy.select2Ajax('project', LUArrivals.project);

            //select2
            cy.select2('transporteur', LUArrivals.transporteur);
            cy.select2('chauffeur', LUArrivals.chauffeur);
            cy.select2('numeroCommandeList', [LUArrivals.firstNumeroCommandeList, LUArrivals.secondNumeroCommandeList]);
            cy.select2('receivers', [LUArrivals.destinataire], 400);
            cy.select2('acheteurs', [LUArrivals.firstAcheteurs, LUArrivals.secondAcheteurs], 300);

            //input
            cy.typeInModalInputs(selectorModal, {
                noTracking: LUArrivals.noTracking,
                noProject: LUArrivals.noProject,
            })

            cy.get('input[name=packs]')
                .first()
                .click()
                .clear()
                .type(`${numberPacksNature1}`);
            cy.get('input[name=packs]')
                .last()
                .click()
                .clear()
                .type(`${numberPacksNature2}`);

            //select
            cy.selectInModal(selectorModal, 'type', LUArrivals.type);
            cy.selectInModal(selectorModal, 'status', LUArrivals.statut);
            cy.selectInModal(selectorModal, 'businessUnit', LUArrivals.businessUnit);

            //checkbox
            cy.checkCheckbox(selectorModal, 'input[name=customs]', true);
            cy.checkCheckbox(selectorModal, 'input[name=frozen]', true);
            cy.checkCheckbox(selectorModal, 'input[name=printArrivage]', false);
            cy.checkCheckbox(selectorModal, 'input[name=printPacks]', false);

            // comment
            cy.get('.ql-editor')
                .click()
                .type(LUArrivals.comment);

            // put file in the input
            cy.get('input[type=file]')
                .selectFile(`cypress/fixtures/${LUArrivals.file}`, {force: true});

            cy.getTheDate().then(logisticUnitsArrivalCreationDate => {
                cy.wrap(logisticUnitsArrivalCreationDate).as('logisticUnitsArrivalCreationDate');
            })

            cy.closeAndVerifyModal(selectorModal, null, 'arrivage_new', true);

            cy.get('#alert-modal', {timeout: 30000})
                .should('be.visible');

            cy.preventPageLoading();
        })

    it("should check the new logistic units arrivals created", () => {
        const propertiesMaps = {
            'Type': 'type',
            'Statut': 'statut',
            'Fournisseur': 'fournisseur',
            'Transporteur': 'transporteur',
            'Chauffeur': 'chauffeur',
            'N° tracking transporteur': 'noTracking',
            'Destinataire(s)': 'destinataire',
            'Numéro de projet': 'noProject',
            'Business unit': 'businessUnit',
            "Nombre d'UL": "numberPacks",
        }

        cy.checkDataInDatatable(LUArrivals, 'type', 'arrivalsTable', propertiesMaps, ['project', 'comment', 'dropLocation', 'file', 'firstAcheteurs', 'secondAcheteurs', 'firstNumeroCommandeList', 'secondNumeroCommandeList'])
    })


    it("should add a new dispute", () => {
        const arrivalsTable = 'table#arrivalsTable';
        const selectorTablePacks = '#tablePacks';
        const selectorModalNewLitige = '#modalNewLitige';

        cy.get(`${arrivalsTable} tbody tr`).last().click();

        cy.wait('@packs_api')

        // get the first and the last logistic units to use them in the dispute
        cy.get(`${selectorTablePacks} tbody tr`).first().find('td').eq(2).then(($text) => {
            const firstLU = $text.text();
            cy.wrap(firstLU).as('firstLU');
        })
        cy.get(`${selectorTablePacks} tbody tr`).last().find('td').eq(2).then(($text) => {
            const secondLU = $text.text();
            cy.wrap(secondLU).as('secondLU');
        })

        // open the modal to add a new dispute
        cy.get('button.new-dispute-modal')
            .click()
            .wait('@new_dispute_template', {timeout: 10000});
        cy.get(selectorModalNewLitige, {timeout: 10000})
            .should('be.visible');

        cy.get(`${selectorModalNewLitige} [name=disputeType]`)
            .select(dispute.type);

        // remove the reporter if it's already selected
        cy.get(selectorModalNewLitige).then(($modal) => {
            if ($modal.find('[name=disputeReporter]').siblings('.select2').find('.select2-selection__clear').length) {
                cy.get('[name=disputeReporter]')
                    .siblings('.select2')
                    .find('.select2-selection__clear')
                    .click();
                cy.get(`[name=disputeReporter]`)
                    .siblings('.select2')
                    .click();
            }
        })
        // and select the new one
        cy.select2Ajax('disputeReporter', dispute.reporter);

        cy.get(`${selectorModalNewLitige} [name=disputeStatus]`)
            .select(dispute.status);

        // fill the dispute packs with the first and the last logistic units selected before
        cy.get('@firstLU').then((firstLU) => {
            cy.get('@secondLU').then((secondLU) => {
                cy.select2('disputePacks', [firstLU, secondLU]);
            })
        })

        cy.get('#submitNewLitige')
            .click()
            .wait('@dispute_new');

        const propertiesMap = {
            'Type': 'type',
            'Statut': 'status',
        }

        // check the dispute created in the datatable
        cy.checkDataInDatatable(dispute, 'type', 'tableArrivageLitiges_wrapper', propertiesMap, ['reporter']);
    })

    it("should edit a dispute", () => {
        const modalEditLitige = '#modalEditLitige';
        const selectorTablePacks = '#tablePacks';

        cy.get('table#arrivalsTable tbody tr')
            .last()
            .click();

        cy.wait('@packs_api')

        // get the first and the last logistic units to use them in the dispute modification
        cy.get(`${selectorTablePacks} tbody tr`).first().find('td').eq(2).then(($text) => {
            const firstLU = $text.text();
            cy.wrap(firstLU).as('firstLU');
        })
        cy.get(`${selectorTablePacks} tbody tr`).last().find('td').eq(2).then(($text) => {
            const secondLU = $text.text();
            cy.wrap(secondLU).as('secondLU');
        })

        cy.wait('@arrivageLitiges_api')

        // click on the last dispute to edit it
        cy.get('#tableArrivageLitiges tbody tr').last()
            .click().wait('@litige_api_edit', {timeout: 8000});

        // edit the dispute
        cy.get(`${modalEditLitige} [name=disputeType]`)
            .select(disputeChanged.type);
        cy.get(`${modalEditLitige} [name=disputeStatus]`)
            .select(disputeChanged.status);
        cy.get(`${modalEditLitige} [name=disputeReporter]`)
            .siblings('.select2')
            .find('.select2-selection__clear')
            .click();
        cy.get(`${modalEditLitige} [name=disputeReporter]`)
            .siblings('.select2')
            .click();
        cy.select2Ajax('disputeReporter', disputeChanged.reporter);

        // remove the packs if they are already selected
        cy.get(`${modalEditLitige}`).then(($modal) => {
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

        // fill the dispute packs with the first and the last logistic units selected before
        cy.get('@firstLU').then((firstLU) => {
            cy.get('@secondLU').then((secondLU) => {
                cy.select2('pack', [firstLU, secondLU]);
            })
        })

        cy.closeAndVerifyModal(modalEditLitige, 'submitEditLitige', 'litige_edit_arrivage');

        const propertiesMap = {
            'Type': 'type',
            'Statut': 'status',
        }

        // check the dispute created in the datatable
        cy.checkDataInDatatable(disputeChanged, 'type', 'tableArrivageLitiges_wrapper', propertiesMap, ['reporter']);

    })

    it("should add a new logistic units", () => {
        const selectorModalAddPacks = '#modalAddPacks';
        // click on the last logistic units arrivals to add a new logistic units
        cy.get('#arrivalsTable tbody tr')
            .last()
            .click();
        // wait for the packs to be loaded in datatable
        cy.wait('@packs_api');

        // open the modal to add a new logistic units
        cy.get("[data-target='#modalAddPacks']")
            .click();

        cy.get(`${selectorModalAddPacks} [name=project]`)
            .select(newLU.project);

        // get all inputs[name=pack] and fill them with the random number of logistic units
        cy.get('input[name=pack]').each(($input) => {
            cy.wrap($input)
                .click()
                .clear()
                .type(`${Math.floor(Math.random() * 2 + 1)}`);
        })

        cy.preventPageLoading();

        // close the modal and verify the response
        cy.closeAndVerifyModal(selectorModalAddPacks, 'submitAddPacks', 'arrivage_add_pack');

    })

    it("should check the new logistic units", () => {
        const selectorTablePacks = '#tablePacks';
        const selectorArrivalsTable = '#arrivalsTable';

        // get logistic units number to check it after
       getColumnIndexByColumnName("N° d'arrivage UL",'arrivalsTable').then((index) => {
           cy.get('#arrivalsTable tbody tr').last().find('td').eq(index).then(($text) => {
               const logisticUnitsNumber = $text.text();
               cy.wrap(logisticUnitsNumber).as('logisticUnitsNumber');
           })
       })

        // click on the last logistic units arrivals to check the new logistic units
        cy.get(`${selectorArrivalsTable} tbody tr`).last().click();

        cy.wait('@packs_api');

        const runout = ['NATURE 1', 'NATURE 2']
        const regex = new RegExp(`${runout.join('|')}`, 'g')

        getColumnIndexByColumnName("Nature", 'tablePacks').then((index) => {
            cy.get(`${selectorTablePacks} tbody tr`)
                .last()
                .find('td')
                .eq(index)
                .contains(regex);
        });
        getColumnIndexByColumnName("Unités logistiques", 'tablePacks').then((index) => {
            cy.get('@logisticUnitsNumber').then((logisticUnitsNumber) => {
                cy.get(`${selectorTablePacks} tbody tr`)
                    .last()
                    .find('td')
                    .eq(index)
                    .contains(logisticUnitsNumber)
            })
        });
        getColumnIndexByColumnName("Projet", 'tablePacks').then((index) => {
            cy.get(`${selectorTablePacks} tbody tr`)
                .last()
                .find('td')
                .eq(index)
                .contains(newLU.project);
        });
    })

    it("should edit a logistic units arrivals", () => {
        const selectorModalEditArrivage = '#modalEditArrivage';
        // click on the last logistic units arrivals to edit it
        cy.get('table#arrivalsTable tbody tr').last().click();

        cy.get('button.split-button')
            .click()
            .wait('@arrivage_edit_api');

        // select2ajax
        cy.select2Ajax('fournisseur', LUArrivalsChanged.fournisseur, '', true, '', false);
        cy.select2Ajax('transporteur', LUArrivalsChanged.transporteur, '', true, '', false);
        cy.select2Ajax('chauffeur', LUArrivalsChanged.chauffeur, '', true, '', false);

        // remove the first and the second numeroCommandeList if they are already selected
        cy.clearSelect2("numeroCommandeList", "modalEditArrivage");
        cy.clearSelect2("acheteurs", "modalEditArrivage");
        cy.clearSelect2("receivers", "modalEditArrivage");

        // refill the select2
        cy.select2('numeroCommandeList', [LUArrivalsChanged.firstNumeroCommandeList, LUArrivalsChanged.secondNumeroCommandeList]);
        cy.select2('acheteurs', [LUArrivalsChanged.firstAcheteurs, LUArrivalsChanged.secondAcheteurs]);
        cy.select2('receivers', [LUArrivalsChanged.destinataire], 300);

        cy.get(`${selectorModalEditArrivage} select[name=statut]`)
            .select(LUArrivalsChanged.statut, {force: true});
        cy.get('[name=businessUnit]')
            .select(LUArrivalsChanged.businessUnit, {force: true});

        // type in inputs with name attribute
        cy.typeInModalInputs(selectorModalEditArrivage, {
            noProject: LUArrivalsChanged.noProject,
            noTracking: LUArrivalsChanged.noTracking,
        });
        // fill comment
        cy.get('.ql-editor')
            .click()
            .clear()
            .type(LUArrivalsChanged.comment);

        // remove the file if it's already selected
        cy.get(selectorModalEditArrivage).then(($modal) => {
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
        // put file in the input
        cy.get('input[type=file]')
            .selectFile(`cypress/fixtures/${LUArrivalsChanged.file}`, {force: true});

        // close the modal and verify the response
        cy.closeAndVerifyModal(selectorModalEditArrivage, 'submitEditArrivage', 'arrivage_edit');
    })

    it("should check the modification made on the logistic units arrivals", () => {
        cy.get('table#arrivalsTable tbody tr')
            .last()
            .click();

        const fieldsToCheck = [
            { title: 'Statut', value: capitalizeFirstLetter(LUArrivalsChanged.statut) },
            { title: 'Fournisseur', value: LUArrivalsChanged.fournisseur },
            { title: 'Transporteur', value: LUArrivalsChanged.transporteur },
            { title: 'Chauffeur', value: LUArrivalsChanged.chauffeur },
            { title: 'N° tracking transporteur', value: LUArrivalsChanged.noTracking },
            { title: 'N° commande / BL', value: [LUArrivalsChanged.firstNumeroCommandeList, LUArrivalsChanged.secondNumeroCommandeList] },
            { title: 'Destinataire(s)', value: LUArrivalsChanged.destinataire },
            { title: 'Acheteur(s)', value: [LUArrivalsChanged.firstAcheteurs, LUArrivalsChanged.secondAcheteurs] },
            { title: 'Numéro de projet', value: LUArrivalsChanged.noProject },
            { title: 'Business unit', value: LUArrivalsChanged.businessUnit },
            { title: 'Commentaire', value: LUArrivalsChanged.comment }
        ];

        fieldsToCheck.forEach(({ title, value }) => {
            if (Array.isArray(value)) {
                cy.get(`[title='${title}']`)
                    .parent()
                    .siblings()
                    .should('contain', value[0])
                    .and('contain', value[1]);
            } else {
                cy.get(`[title='${title}']`)
                    .parent()
                    .siblings()
                    .contains(value);
            }
        });

        cy.get('[title=Douane]').parent().siblings().contains('Non');
        cy.get('[title=Congelé]').parent().siblings().contains('Non');
        cy.get('a[download]').contains(LUArrivalsChanged.file);
    })
})

