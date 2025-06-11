import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Nature', () => {
    beforeEach(() => {
        interceptRoute(routes.nature_api);
        interceptRoute(routes.nature_new);
        interceptRoute(routes.nature_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'nature_index');
        uncaughtException();
    })

    it('should add a new nature', () => {

        const newNature = {
            label: 'OUTILS',
            code: 'OUTILS',
            quantity: '1',
        }
        const propertiesMap = {
            'Libellé': 'label',
            'Code': 'code',
            "Quantité par défaut de l'arrivage": 'quantity',
        }
        const selectorModal = '#modalNewNature';

        cy.openModal(selectorModal, 'label')

        cy.get(`${selectorModal}`).should('be.visible', {timeout: 8000}).then(() => {

            // edit values
            const languageInput = "Français"
            cy.get(`#modalNewNature [data-cypress=${languageInput}]`)
                .wait(400)
                .clear()
                .type(newNature.label);

            cy.typeInModalInputs(selectorModal, newNature, ['label']);

            // submit form & wait reponse
            cy.closeAndVerifyModal(selectorModal, null, routes.nature_new.alias, true);
        })
        cy.wait('@nature_api');

        // check datatable after edit
        cy.checkDataInDatatable(newNature, 'label', 'tableNatures', propertiesMap);
    })

    it('should edit a nature', () => {

        const natureToEdit = ['OUTILS']
        const newNatures = [{
            label: 'COLIS',
            code: 'COLIS',
            quantity: '10',
        }]
        const propertiesMap = {
            'Libellé': 'label',
            'Code': 'code',
            "Quantité par défaut de l'arrivage": 'quantity',
        }
        const selectorModal = '#modalEditNature';

        cy.wait('@nature_api');

        natureToEdit.forEach((natureToEditName, index) => {
            cy.clickOnRowInDatatable('tableNatures', natureToEditName);

            cy.get(selectorModal).should('be.visible');

            // edit values
            const languageInput = "Français"
            cy.get(`#modalEditNature [data-cypress=${languageInput}]`).click()
                .wait(400)
                .clear()
                .type(newNatures[index].label);

            cy.typeInModalInputs(selectorModal, newNatures[index], ['label']);

            // submit form
            cy.closeAndVerifyModal(selectorModal, null, routes.nature_edit.alias, true);
            cy.wait('@nature_api');

            cy.checkDataInDatatable(newNatures[index], 'label', 'tableNatures', propertiesMap)
        })
    })
})
