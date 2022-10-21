$(document).ready(function() {
    let pathCustomer = Routing.generate('customer_api', true);
    let tableCustomerConfig = {
        ajax: {
            "url": pathCustomer,
            "type": "POST"
        },
        order: [[ 1, "asc" ]],
        columns: [
            { "data": 'Delete', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false },
            { "data": 'Customer', 'name': 'Client', 'title': 'Client'},
            { "data": 'Address', 'name': 'Adresse', 'title': 'Adresse' },
            { "data": 'PhoneNumber', 'name': 'Téléphone', 'title': 'Téléphone' },
            { "data": 'Email', 'name': 'Email', 'title': 'Email'},
            { "data": 'Fax', 'name': 'Fax', 'title': 'Fax'},
        ],
        drowConfig: {
            needsRowClickAction: true,
        },
    };
    let tableCustomer = initDataTable('customerTable', tableCustomerConfig);
});
