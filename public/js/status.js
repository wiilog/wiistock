$(function () {
    let pathStatus = Routing.generate('status_param_api', true);
    let tableStatus = $('#tableStatus').DataTable({
        "language": {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathStatus,
            "type": "POST"
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Categorie', 'title': 'Catégorie'},
            {"data": 'Label', 'title': 'Libellé'},
            {"data": 'Comment', 'title': 'Commentaire'},
            {"data": 'Treated', 'title': 'Statut litige traité'},
            {"data": 'NotifToBuyer', 'title': 'Envoi de mails'},
            {"data": 'Order', 'title': 'Ordre'},
        ],
        order: [
            [6, 'asc']
        ],
        rowCallback: function (row, data) {
            initActionOnRow(row);
        },
    });

    let modalNewStatus = $("#modalNewStatus");
    let submitNewStatus = $("#submitNewStatus");
    let urlNewStatus = Routing.generate('status_new', true);
    InitialiserModal(modalNewStatus, submitNewStatus, urlNewStatus, tableStatus, displayErrorStatus, false);

    let modalEditStatus = $('#modalEditStatus');
    let submitEditStatus = $('#submitEditStatus');
    let urlEditStatus = Routing.generate('status_edit', true);
    InitialiserModal(modalEditStatus, submitEditStatus, urlEditStatus, tableStatus, displayErrorStatusEdit, false, false);

    let modalDeleteStatus = $("#modalDeleteStatus");
    let submitDeleteStatus = $("#submitDeleteStatus");
    let urlDeleteStatus = Routing.generate('status_delete', true)
    InitialiserModal(modalDeleteStatus, submitDeleteStatus, urlDeleteStatus, tableStatus);
});

function displayErrorStatus(data) {
    let modal = $("#modalNewStatus");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}

function displayErrorStatusEdit(data) {
    let modal = $("#modalEditStatus");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}
