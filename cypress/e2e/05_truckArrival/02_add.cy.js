import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user= Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";
import {getColumnIndexByColumnName} from "../../support/utils";

const numberPacksNature1 = 1;
const numberPacksNature2 = 4;
const newTrackingNumber = '98765'
const truckArrival = {
    carrier: 'TRANSPORTEUR',
    driver: "Georges",
    registrationNumber: "CL-010-RA",
    unloadingLocation: "GT",
    trackingNumbers: ['12345', '54321'],
}
const arrival = {
    fournisseur: 'FOURNISSEUR',
    transporteur: 'TRANSPORTEUR',
    chauffeur: 'Georges',
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
describe('Add a new truck arrival', () => {

    beforeEach(() => {
        interceptRoute(routes.number_carrier_api);
        interceptRoute(routes.transporteur_api);
        interceptRoute(routes.truck_arrival_list);
        interceptRoute(routes.truck_arrival_api);
        interceptRoute(routes.arrivage_new);
        interceptRoute(routes.arrival_packs_api);

        cy.login(user);
        cy.visit('/');
        uncaughtException();
    });

    it('should add a new truck arrival with right field', () => {
        cy.navigateInNavMenu('traca', 'truck_arrival_index');

        const selectorModal = '#modalNewTruckArrival';

        cy.get('button[name=new-truck-arrival]').click()

        cy.get(selectorModal).should('be.visible', {timeout: 8000}).then(() => {

            cy.select2Ajax('driver', truckArrival.driver, "modalNewTruckArrival");
            cy.select2Ajax('carrier', truckArrival.carrier, "modalNewTruckArrival");
            cy.select2Ajax('unloadingLocation', truckArrival.unloadingLocation, "modalNewTruckArrival");

            cy.typeInModalInputs(selectorModal, {
                registrationNumber : truckArrival.registrationNumber
            });

            cy.select2('trackingNumbers', truckArrival.trackingNumbers);

            cy.closeAndVerifyModal(selectorModal, null, routes.truck_arrival_list.alias, false, 'button[name=saveWithoutRedirection]');
        })

    })

    it("should check the new truck arrival", () => {
        cy.navigateInNavMenu('traca', 'truck_arrival_index');

        cy.get('div.filters-container input[name=carrierTrackingNumber')
            .type(truckArrival.trackingNumbers[0]);

        cy.get('button.filters-submit').click();

        cy.checkRequestStatusCode('truck_arrival_api_list', 200)
            .then(() => {
                const columnName = "Numéro d'arrivage camion";
                const tableId = "truckArrivalsTable_wrapper";

                getColumnIndexByColumnName(columnName, tableId).then((index) => {
                    cy.get(`tbody>tr:eq(1)`).get(`td:eq(${index})`).invoke('text').as('noTruckArrival')
                });
            })
        });

    it("should add a new logistic units arrivals", function () {

        cy.navigateInNavMenu('traca', 'arrivage_index');

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

        let buttonSelector = `${selectorModal} button[type=submit]`;

        // check error we should have
        cy.get(buttonSelector).click().then(() => {
            cy.get('div.error-msg', {timeout: 5000})
                .should('be.visible');
        })

        cy.select2Ajax('noTracking', truckArrival.trackingNumbers[0], "modalNewArrivage", '/select/*', true, false);

        cy.get('#modalNewArrivage [name=noTruckArrival]').contains(this.noTruckArrival);

        cy.select2NewComponentAjax('noTracking', newTrackingNumber, "modalNewArrivage", 2);

        cy.closeAndVerifyModal(selectorModal, null, 'arrivage_new', true);
    })

    it("should check the new truck arrival with the truck number added", function () {

        cy.navigateInNavMenu('traca', 'truck_arrival_index');

        cy.get('.clearFiltersBtn').click();

        cy.get('div.filters-container input[name=truckArrivalNumber')
            .type(this.noTruckArrival)

        cy.get('button.filters-submit').click();

        cy.checkRequestStatusCode('truck_arrival_api_list', 200)
            .then(() => {
                cy.clickOnRowInDatatable("truckArrivalsTable_wrapper" , this.noTruckArrival);

            })
    });
});
