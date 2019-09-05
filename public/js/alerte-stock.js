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
    'rowCallback': function (row, data) {
        if (data.Statut == 'inactive') {
            $(row).addClass('bg-secondary text-white')
        } else if (data.QuantiteStock <= data.SeuilSecurite) {
            $(row).addClass('bg-danger');
        } else if (data.QuantiteStock <= data.SeuilAlerte) {
            $(row).addClass('bg-warning');
        }
    },
    columns: [
        { "data": 'Code', 'title': 'Code' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'QuantiteStock', 'title': 'Quantité en stock' },
        { "data": "SeuilAlerte", 'title': "Seuil d'alerte" },
        { "data": 'SeuilSecurite', 'title': 'Seuil de sécurité' },
        { "data": 'Utilisateur', 'title': 'Utilisateur' },
        // { "data": 'Statut', 'title': 'Statut' },
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
    let $limitAlert = $modalBody.find('#limitAlert');

    $limitSecurity.attr('max', $limitAlert.val());
    $limitAlert.attr('min', $limitSecurity.val());
}

function displayErrorAlertStock(data) {
    let modal = $("#modalNewAlerteStock");
    let msg = data.msg;
    displayError(modal, msg, data.success);
}

function filterActiveAlerts($elem)
{
    if ($elem.is(':checked')) {
        // tableAlerteStock
        // .columns()
        // .search()
        // .draw();
        // TODO CG
    } else {
    }
}
