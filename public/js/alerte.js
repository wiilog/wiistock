$('.select2').select2();

let pathAlerte = Routing.generate('alerte_api', true);
let tableAlerte = $('#tableAlerte_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url":pathAlerte,
        "type": "POST"
    },
    'rowCallback': function (row, data) {
        if (data.QuantiteStock <= data.SeuilSecurite) {
            $(row).addClass('bg-danger');
        } else if (data.QuantiteStock <= data.SeuilAlerte) {
            $(row).addClass('bg-warning');
        }
    },
    columns: [
        { "data": 'Code', 'title': 'Code' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'QuantiteStock', 'title': 'Quantité en stock' },
        { "data": "SeuilAlerte", 'title': 'Seuil d\'alerte' },
        { "data": 'SeuilSecurite', 'title': 'Seuil de sécurité' },
        { "data": 'Utilisateur', 'title': 'Utilisateur' },
        { "data": 'Statut', 'title': 'Statut' },
        { "data": 'Actions', 'title': 'Actions' },
    ],
});

let modalNewAlerte = $("#modalNewAlerte"); 
let submitNewAlerte = $("#submitNewAlerte");
let urlNewAlerte = Routing.generate('alerte_new', true);
InitialiserModal(modalNewAlerte, submitNewAlerte, urlNewAlerte, tableAlerte, displayErrorAlert, false);

let ModalDeleteAlerte = $("#modalDeleteAlerte");
let SubmitDeleteAlerte = $("#submitDeleteAlerte");
let urlDeleteAlerte = Routing.generate('alerte_delete', true)
InitialiserModal(ModalDeleteAlerte, SubmitDeleteAlerte, urlDeleteAlerte, tableAlerte);

let modalModifyAlerte = $('#modalEditAlerte');
let submitModifyAlerte = $('#submitEditAlerte');
let urlModifyAlerte = Routing.generate('alerte_edit', true);
InitialiserModal(modalModifyAlerte, submitModifyAlerte, urlModifyAlerte, tableAlerte);

function updateLimitsMinMax(elem)
{
    let $modalBody = elem.closest('.modal-body');
    let $limitSecurity = $modalBody.find('#limitSecurity');
    let $limitAlert = $modalBody.find('#limitAlert');

    $limitSecurity.attr('max', $limitAlert.val());
    $limitAlert.attr('min', $limitSecurity.val());
}

function displayErrorAlert(data) {
    let modal = $("#modalNewAlerte");
    let msg = data.msg;
    displayError(modal, msg, data.success);
}
