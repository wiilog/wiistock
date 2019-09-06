$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Utilisateur',
    }
});

let pathAlerteStock = Routing.generate('alerte_stock_api', true);
let tableAlerteStock = $('#tableAlerteStock_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url":pathAlerteStock,
        "type": "POST"
    },
    'drawCallback': function () {
        tableAlerteStock.column('Active:name').visible(false);
    },
    'initComplete': function() {
        // applique les filtres si pré-remplis
        let filterActive = $('#filter-active').hasClass('active');
        if (filterActive) {
            tableAlerteStock
                .columns('Active:name')
                .search('true')
                .draw();
        }
    },
    columns: [
        { "data": 'Code', 'title': 'Code' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'QuantiteStock', 'title': 'Quantité en stock' },
        { "data": "SeuilAlerte", 'title': "Seuil d'alerte" },
        { "data": 'SeuilSecurite', 'title': 'Seuil de sécurité' },
        { "data": 'Utilisateur', 'title': 'Utilisateur' },
        { "data": 'Active', 'name': 'Active', 'title': 'Active' },
        { "data": 'Actions', 'title': 'Actions' },
    ],
});

let modalNewAlerteStock = $("#modalNewAlerteStock");
let submitNewAlerteStock = $("#submitNewAlerteStock");
let urlNewAlerte = Routing.generate('alerte_stock_new', true);
InitialiserModal(modalNewAlerteStock, submitNewAlerteStock, urlNewAlerte, tableAlerteStock, displayErrorAlertStock, false);

let ModalDeleteAlerteStock = $("#modalDeleteAlerteStock");
let SubmitDeleteAlerteStock = $("#submitDeleteAlerteStock");
let urlDeleteAlerteStock = Routing.generate('alerte_stock_delete', true)
InitialiserModal(ModalDeleteAlerteStock, SubmitDeleteAlerteStock, urlDeleteAlerteStock, tableAlerteStock);

let modalModifyAlerteStock = $('#modalEditAlerteStock');
let submitModifyAlerteStock = $('#submitEditAlerteStock');
let urlModifyAlerteStock = Routing.generate('alerte_stock_edit', true);
InitialiserModal(modalModifyAlerteStock, submitModifyAlerteStock, urlModifyAlerteStock, tableAlerteStock);

function updateLimitsMinMax(elem)
{
    let $modalBody = elem.closest('.modal-body');
    let $limitSecurity = $modalBody.find('#limitSecurity');
    let $limitWarning = $modalBody.find('#limitWarning');

    $limitSecurity.attr('max', $limitWarning.val());
    $limitWarning.attr('min', $limitSecurity.val());
}

function displayErrorAlertStock(data) {
    let modal = $("#modalNewAlerteStock");
    let msg = data.msg;
    displayError(modal, msg, data.success);
}
