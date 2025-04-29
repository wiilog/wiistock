import routes, {interceptRoute} from "/cypress/support/utils/routes";
const user = Cypress.config('user');
import {uncaughtException} from "/cypress/support/utils";

describe('Add and edit components in Referentiel > Clients', () => {
    beforeEach(() => {
        interceptRoute(routes.customer_api);
        interceptRoute(routes.customer_new);
        interceptRoute(routes.customer_edit);

        cy.login(user);
        cy.visit('/');
        cy.navigateInNavMenu('referentiel', 'customer_index');
        uncaughtException();
    })

    it('should add a new customer', () => {

        const newCustomer = {
            name: 'Toto',
            address: 'Bègles',
            recipient: 'PAS',
            'phone-number': '0218923090',
            email: 'admin@wiilog.fr',
            fax: '0218923091',
        }
        const propertiesMap = {
            'Adresse': 'address',
            'Destinataire': 'recipient',
            'Téléphone': 'phone-number',
            'Email': 'email',
            'Fax': 'fax',
        }
        const selectorModal = '#modalNewCustomer';
        cy.openModal(selectorModal, 'name');

        cy.get(selectorModal).should('be.visible', {timeout: 8000}).then(() => {

            // edit values (wait for input be selected i don't know why it doesn't work without it)
            cy.get(`${selectorModal} input[name=name]`).wait(500).type(newCustomer.name);
            cy.get(`${selectorModal} textarea[name=address]`).type(newCustomer.address);
            cy.typeInModalInputs(selectorModal, newCustomer, ['address', 'name']);

            cy.closeAndVerifyModal(selectorModal, 'submitNewCustomer', 'customer_new', true);
        })
        // check datatable is reloaded
        cy.wait('@customer_api');

        cy.checkDataInDatatable(newCustomer, 'name', 'customerTable', propertiesMap, ['name']);
    })

    it('should edit a customer', () => {

        const customerToEdit = ['Client']
        let newCustomers = [{
            name: 'RE',
            address: 'Bordeaux',
            recipient: 'POND',
            'phone-number': '0218923092',
            email: 'tata@wiilog.fr',
            fax: '0218923093',
        }]
        const propertiesMap = {
            'Adresse': 'address',
            'Destinataire': 'recipient',
            'Téléphone': 'phone-number',
            'Email': 'email',
            'Fax': 'fax',
        }
        const selectorModal = '#modalEditCustomer';
        cy.wait('@customer_api');

        customerToEdit.forEach((customerToEditName, index) => {
            cy.clickOnRowInDatatable('customerTable', customerToEditName);
            cy.get(selectorModal).should('be.visible');

            // edit values
            cy.get(`${selectorModal} [name=name]`).clear().click().type(newCustomers[index].name);
            cy.get(`${selectorModal} [name=address]`).clear().click().type(newCustomers[index].address);
            cy.typeInModalInputs(selectorModal, newCustomers[index], ['address']);

            // submit form
            cy.closeAndVerifyModal(selectorModal, 'submitEditCustomer', 'customer_edit')

            cy.wait('@customer_api');

            cy.checkDataInDatatable(newCustomers[index], 'name', 'customerTable', propertiesMap, ['name'])
        })
    })
})
