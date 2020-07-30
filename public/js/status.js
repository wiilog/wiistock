$(function () {
    let pathStatus = Routing.generate('status_param_api', true);
    let tableStatusConfig = {
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
            {"data": 'NotifToBuyer', 'title': 'Envoi de mails aux acheteurs'},
            {"data": 'NotifToDeclarant', 'title': 'Envoi de mails au déclarant'},
            {"data": 'Order', 'title': 'Ordre'},
        ],
        order: [
            [7, 'asc']
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    let tableStatus = initDataTable('tableStatus', tableStatusConfig);

    let modalNewStatus = $("#modalNewStatus");
    let submitNewStatus = $("#submitNewStatus");
    let urlNewStatus = Routing.generate('status_new', true);
    InitModal(modalNewStatus, submitNewStatus, urlNewStatus, {tables: [tableStatus]});

    let modalEditStatus = $('#modalEditStatus');
    let submitEditStatus = $('#submitEditStatus');
    let urlEditStatus = Routing.generate('status_edit', true);
    InitModal(modalEditStatus, submitEditStatus, urlEditStatus, {tables: [tableStatus]});

    let modalDeleteStatus = $("#modalDeleteStatus");
    let submitDeleteStatus = $("#submitDeleteStatus");
    let urlDeleteStatus = Routing.generate('status_delete', true)
    InitModal(modalDeleteStatus, submitDeleteStatus, urlDeleteStatus, {tables: [tableStatus]});
});
