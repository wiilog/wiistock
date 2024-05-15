import {initDataTable} from "@app/datatable";

global.validateOrder = validateOrder;
global.deleteOrder = deleteOrder;

$(() => {
    $('#modalDeleteTransferWithLocation').on('show.bs.modal', function (e) {
        Select2Old.location($('.ajax-autocomplete-location'));
    })

    Select2Old.articleReference($(".ajax-autocomplete"));

    const transferOrderId = $(`#transferOrderId`).val();

    initDataTable('tableArticle', {
        ajax: {
            "url": Routing.generate('transfer_order_article_api', {transfer: transferOrderId}, true),
            "type": "POST"
        },
        order: [['Référence', 'desc']],
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'barCode', 'title': 'Code barre'},
            {"data": 'Quantité', 'title': 'Quantité'}
        ],
    });

    let modalDeleteTransfer = $("#modalDeleteTransfer");
    let submitDeleteTransfer = $("#submitDeleteTransfer");
    let urlDeleteTransfer = Routing.generate('transfer_order_delete', {id: transferOrderId}, true)
    InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);

    $('.select2').select2();
});

function deleteOrder() {
    const isTreated = $('#transferOrderIsTreated').val() == 1;
    if (!isTreated) {
        const $modal = $('#modalDeleteTransfer');
        deleteRow($(this), $modal, $('#submitDeleteTransfer'));
        $modal.modal('show');
    } else {
        const transferOrderId = $('#transferOrderId').val();
        let modalDeleteTransfer = $("#modalDeleteTransferWithLocation");
        let submitDeleteTransfer = $("#submitDeleteTransferWithLocation");
        let urlDeleteTransfer = Routing.generate('transfer_order_delete', {id: transferOrderId}, true)
        InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);
        $('#modalDeleteTransferWithLocation').modal('show');
    }
}

function validateOrder($button) {
    const transferOrderId = $('#transferOrderId').val();
    let route = Routing.generate('transfer_order_validate', {id: transferOrderId});

    wrapLoadingOnActionButton($button, () => (
        $.post(route, function (response) {
            window.location.href = response.redirect;
            return true;
        })
    ));
}

