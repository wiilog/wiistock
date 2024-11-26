import routes, {interceptRoute} from "/cypress/support/utils/routes";
import {capitalizeFirstLetter, getColumnIndexByColumnName} from "/cypress/support/utils";
const user= Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";


const numberPacksNature1 = 1;
const numberPacksNature2 = 4;
const arrival = {
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

const arrivalChanged = {
    fournisseur: 'SAMSUNG',
    transporteur: 'GT',
    chauffeur: 'Marcel GEORGES',
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

const newUL = {
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

describe('Add and edit logistic units arrivals', () => {

    beforeEach(() => {
        interceptRoute(routes.packs_api);
        interceptRoute(routes.print_arrivage_bar_codes_nature_1);
        interceptRoute(routes.print_arrivage_bar_codes_nature_2);
        interceptRoute(routes.arrivage_new);
        interceptRoute(routes.new_dispute_template);
        interceptRoute(routes.dispute_new);
        interceptRoute(routes.arrival_diputes_api);
        interceptRoute(routes.arrival_dispute_api_edit);
        interceptRoute(routes.arrival_edit_dispute);
        interceptRoute(routes.arrivage_add_pack);
        interceptRoute(routes.printPacks);
        interceptRoute(routes.arrivage_edit_api);
        interceptRoute(routes.arrivage_edit);

        const downloadsFolder = Cypress.config('downloadsFolder');
        cy.exec(`del /q ${downloadsFolder}\\*`, {failOnNonZeroExit: false});

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('traca', 'arrivage_index');
        uncaughtException();

    })

    it("should add a new logistic units arrivals without the redirect after a new arrival", () => {

        const selectorModal = '#modalNewArrivage';

        // click btn new Arrival and verify the modal is visible
        cy.openModal(selectorModal, 'noProject', 'button[name=new-arrival]')

        //select2ajax
        cy.select2Ajax('fournisseur', arrival.fournisseur, "modalNewArrivage");

        cy.select2Ajax('dropLocation', arrival.dropLocation, "modalNewArrivage");
        cy.select2Ajax('project', arrival.project);

        //select2
        cy.select2('transporteur', arrival.transporteur);
        cy.select2('chauffeur', arrival.chauffeur);
        cy.select2('numeroCommandeList', [arrival.firstNumeroCommandeList, arrival.secondNumeroCommandeList]);
        cy.select2('receivers', [arrival.destinataire], 400);
        cy.select2('acheteurs', [arrival.firstAcheteurs, arrival.secondAcheteurs], 300);

        //input
        cy.typeInModalInputs(selectorModal, {
            noTracking: arrival.noTracking,
            noProject: arrival.noProject,
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
        cy.selectInModal(selectorModal, 'type', arrival.type);
        cy.selectInModal(selectorModal, 'status', arrival.statut);
        cy.selectInModal(selectorModal, 'businessUnit', arrival.businessUnit);

        //checkbox
        cy.checkCheckbox(selectorModal, 'input[name=customs]', true);
        cy.checkCheckbox(selectorModal, 'input[name=frozen]', true);
        cy.checkCheckbox(selectorModal, 'input[name=printArrivage]', false);
        cy.checkCheckbox(selectorModal, 'input[name=printPacks]', false);

        // comment
        cy.get('.ql-editor')
            .click()
            .type(arrival.comment);

        // put file in the input
        cy.get('input[type=file]')
            .selectFile(`cypress/fixtures/${arrival.file}`, {force: true});

        cy.getTheDate().then(logisticUnitsArrivalCreationDate => {
            cy.wrap(logisticUnitsArrivalCreationDate).as('logisticUnitsArrivalCreationDate');
        })

        cy.closeAndVerifyModal(selectorModal, null, 'arrivage_new', true);

        cy.get('#alert-modal', {timeout: 30000})
            .should('be.visible');

        cy.preventPageLoading();
    })

    it("should check all checkboxes in column management modal ", () => {
        cy.checkAllInColumnManagement('.arrival-mode-container');
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

        cy.checkDataInDatatable(arrival, 'type', 'arrivalsTable', propertiesMaps, ['project', 'comment', 'dropLocation', 'file', 'firstAcheteurs', 'secondAcheteurs', 'firstNumeroCommandeList', 'secondNumeroCommandeList'])
    })

    it("should add a new dispute", () => {
        const arrivalsTable = 'table#arrivalsTable';
        const selectorTablePacks = '#tablePacks';
        const selectorModalNewLitige = '#modalNewLitige';

        cy.get(`${arrivalsTable} tbody tr`).last().find('td').eq(2).click();

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

        cy.select2Ajax('disputeType',dispute.type, '', true, '/select/*', false);

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

        cy.select2Ajax('disputeStatus',dispute.status, '', true, '/select/*', false);

        // fill the dispute packs with the first and the last logistic units selected before
        cy.get('@firstLU').then((firstLU) => {
            cy.get('@secondLU').then((secondLU) => {
                cy.select2('disputePacks', [firstLU, secondLU]);
            })
        })

        cy.get(selectorModalNewLitige).find('button[type="submit"]')
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

        // bug if we just use .last().click() so we need to use .last().find('td').eq(2).click()
        cy.get('table#arrivalsTable tbody tr')
            .last()
            .find('td')
            .eq(2)
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

        cy.wait('@arrival_diputes_api')

        // click on the last dispute to edit it
        cy.get('#tableArrivageLitiges tbody tr').last()
            .click().wait('@arrival_dispute_api_edit', {timeout: 8000});

        // edit the dispute
        cy.select2Ajax('disputeType', disputeChanged.type, '', true, '/select/*', false);
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
        cy.clearSelect2AjaxValues('disputePacks');

        // fill the dispute packs with the first and the last logistic units selected before
        cy.get('@firstLU').then((firstLU) => {
            cy.get('@secondLU').then((secondLU) => {
                cy.select2('disputePacks', [firstLU, secondLU]);
            })
        })

        cy.closeAndVerifyModal(modalEditLitige, 'submitEditLitige', 'arrival_edit_dispute');

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
        // bug if we just use .last().click() so we need to use .last().find('td').eq(2).click()
        cy.get('table#arrivalsTable tbody tr')
            .last()
            .find('td')
            .eq(2)
            .click();

        // wait for the packs to be loaded in datatable
        cy.wait('@packs_api');

        // open the modal to add a new logistic units
        cy.get("[data-target='#modalAddPacks']")
            .click();

        cy.get(`${selectorModalAddPacks} [name=project]`)
            .select(newUL.project);

        // get all inputs[name=pack] and fill them with the random number of logistic units
        cy.get('input[name=pack]').each(($input) => {
            cy.wrap($input)
                .click()
                .clear()
                .type(`${Math.floor(Math.random() * 2 + 1)}`);
        })

        cy.preventPageLoading();

        // close the modal and verify the response
        cy.closeAndVerifyModal(selectorModalAddPacks, null, 'arrivage_add_pack', true);

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
        // bug if we just use .last().click() so we need to use .last().find('td').eq(2).click()
        cy.get(`${selectorArrivalsTable} tbody tr`)
            .last()
            .find('td')
            .eq(2)
            .click();

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
                .contains(newUL.project);
        });
    })

    it("should edit a logistic units arrivals", () => {
        const selectorModalEditArrivage = '#modalEditArrivage';
        // click on the last logistic units arrivals to edit it
        cy.get('table#arrivalsTable tbody tr').last().find('td').eq(2).click()
            .wait('@arrival_diputes_api')
            .wait('@packs_api');

        cy.get('button.split-button')
            .click()
            .wait('@arrivage_edit_api');

        // select2ajax
        cy.select2Ajax('fournisseur', arrivalChanged.fournisseur, '', true, '', false);
        cy.select2Ajax('transporteur', arrivalChanged.transporteur, '', true, '', false);
        cy.select2Ajax('chauffeur', arrivalChanged.chauffeur, '', true, '', false);

        // remove the first and the second numeroCommandeList if they are already selected
        cy.clearSelect2("numeroCommandeList", "modalEditArrivage");
        cy.clearSelect2("acheteurs", "modalEditArrivage");
        cy.clearSelect2("receivers", "modalEditArrivage");

        // refill the select2
        cy.select2('numeroCommandeList', [arrivalChanged.firstNumeroCommandeList, arrivalChanged.secondNumeroCommandeList]);
        cy.select2('acheteurs', [arrivalChanged.firstAcheteurs, arrivalChanged.secondAcheteurs]);
        cy.select2('receivers', [arrivalChanged.destinataire,arrivalChanged.firstAcheteurs], 300);

        cy.get(`${selectorModalEditArrivage} select[name=statut]`)
            .select(arrivalChanged.statut, {force: true});
        cy.get('[name=businessUnit]')
            .select(arrivalChanged.businessUnit, {force: true});

        // type in inputs with name attribute
        cy.typeInModalInputs(selectorModalEditArrivage, {
            noProject: arrivalChanged.noProject,
            noTracking: arrivalChanged.noTracking,
        });
        // fill comment
        cy.get('.ql-editor')
            .click()
            .clear()
            .type(arrivalChanged.comment);

        // remove the file if it's already selected
        cy.get(selectorModalEditArrivage).then(($modal) => {
            if ($modal.find(`a[data-original-title="${arrival.file}"]`).siblings('svg').length) {
                cy.get(`a[data-original-title="${arrival.file}"]`).siblings('svg').then(($elements) => {
                    const numElements = $elements.length;
                    for (let i = 0; i < numElements; i++) {
                        cy.get(`a[data-original-title="${arrival.file}"]`).siblings('svg')
                            .eq(0)
                            .click();
                    }
                });
            }
        })
        // put file in the input
        cy.get('input[type=file]')
            .selectFile(`cypress/fixtures/${arrivalChanged.file}`, {force: true});

        // close the modal and verify the response
        cy.closeAndVerifyModal(selectorModalEditArrivage, null, 'arrivage_edit', true);
    })

    it("should check the modification made on the logistic units arrivals", () => {
        cy.get('table#arrivalsTable tbody tr').last().find('td').eq(2).click();

        const fieldsToCheck = [
            { title: 'Statut', value: capitalizeFirstLetter(arrivalChanged.statut) },
            { title: 'Fournisseur', value: arrivalChanged.fournisseur },
            { title: 'Transporteur', value: arrivalChanged.transporteur },
            { title: 'Chauffeur', value: arrivalChanged.chauffeur.split(' ')[0] },
            { title: 'N° tracking transporteur', value: arrivalChanged.noTracking },
            { title: 'N° commande / BL', value: [arrivalChanged.firstNumeroCommandeList, arrivalChanged.secondNumeroCommandeList] },
            { title: 'Destinataire(s)', value: arrivalChanged.destinataire },
            { title: 'Acheteur(s)', value: [arrivalChanged.firstAcheteurs, arrivalChanged.secondAcheteurs] },
            { title: 'Numéro de projet', value: arrivalChanged.noProject },
            { title: 'Business unit', value: arrivalChanged.businessUnit },
            { title: 'Commentaire', value: arrivalChanged.comment }
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
                    .contains(value, { matchCase: false });
            }
        });

        cy.get('[title=Douane]').parent().siblings().contains('Non');
        cy.get('[title=Congelé]').parent().siblings().contains('Non');
        cy.get('a[download]').contains(arrivalChanged.file);
    })
})
