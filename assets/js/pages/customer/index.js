import Form from "@app/form";
import AJAX, {POST} from "@app/ajax";

let customerTable;

$(function() {
    customerTable = initCustomerTable();
    initializeNewModal(customerTable);
    initializeEditModal(customerTable);
    initializeDeleteModal(customerTable);

});

function initCustomerTable() {
    let pathCustomer = Routing.generate('customer_api', true);
    let tableCustomerConfig = {
        ajax: {
            "url": pathCustomer,
            "type": "POST"
        },
        order: [[ 1, "asc" ]],
        columns: [
            { "data": 'actions', 'title': '', className: 'noVis', orderable: false },
            { "data": 'customer', 'name': 'Client', 'title': 'Client'},
            { "data": 'address', 'name': 'Adresse', 'title': 'Adresse' },
            { "data": 'phoneNumber', 'name': 'Téléphone', 'title': 'Téléphone' },
            { "data": 'email', 'name': 'Email', 'title': 'Email'},
            { "data": 'fax', 'name': 'Fax', 'title': 'Fax'},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    return initDataTable('customerTable', tableCustomerConfig);
}

function initializeNewModal(table) {
    Form.create(`#modalNewCustomer`, {clearOnOpen: true})
        .submitTo(`POST`, `customer_new`, {
            table
        })
}

function initializeEditModal(table) {
    Form.create(`#modalEditCustomer`).submitTo(`POST`, `customer_edit`, {
        table
    })
}

function initializeDeleteModal(table) {
    Form.create(`#modalDeleteCustomer`).submitTo(`POST`, `customer_delete`, {
        table
    })
}

function deleteCustomer(id){
    Modal.confirm({
        ajax: {
            method: 'DELETE',
            route: 'customer_delete',
            params: { 'customer' : id },
        },
        message: 'Voulez-vous réellement supprimer ce client ?',
        title: 'Supprimer le client',
        validateButton: {
            color: 'danger',
            label: 'Supprimer'
        },
        table: customerTable,
    })
}
global.deleteCustomer = deleteCustomer;
