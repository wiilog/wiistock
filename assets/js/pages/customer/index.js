import Form from "@app/form";
import Modal from "@app/modal";
import {DELETE, POST} from "@app/ajax";
import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";

global.deleteCustomer = deleteCustomer;

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
            { "data": 'recipient', 'name': 'Destinataire', 'title': 'Destinataire' },
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
    Form.create(`#modalNewCustomer`, {resetView: ['open', 'close']})
        .submitTo(POST, `customer_new`, {
            tables: [table]
        })
}

function initializeEditModal(table) {
    Form.create(`#modalEditCustomer`).submitTo(POST, `customer_edit`, {
        tables: [table]
    })
}

function initializeDeleteModal(table) {
    Form.create(`#modalDeleteCustomer`).submitTo(POST, `customer_delete`, {
        tables: [table]
    })
}

function deleteCustomer(id){
    Modal.confirm({
        ajax: {
            method: DELETE,
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
