let tableArticles;

$(document).ready(() => {


    $('#modalDeleteTransferWithLocation').on('show.bs.modal', function (e) {
        Select2.location($('.ajax-autocomplete-location'));
    })

    Select2.articleReference($(".ajax-autocomplete"));

    tableArticle = initDataTable('tableArticle', {
        ajax: {
            "url": Routing.generate('transfer_order_article_api', {transfer: id}, true),
            "type": "POST"
        },
        order: [[1, 'desc']],
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
    let urlDeleteTransfer = Routing.generate('transfer_order_delete', {id}, true)
    InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);
});

function deleteOrder() {
    if (!isTreated) {
        const $modal = $('#modalDeleteTransfer');
        deleteRow($(this), $modal, $('#submitDeleteTransfer'));
        $modal.modal('show');
    } else {
        let modalDeleteTransfer = $("#modalDeleteTransferWithLocation");
        let submitDeleteTransfer = $("#submitDeleteTransferWithLocation");
        let urlDeleteTransfer = Routing.generate('transfer_order_delete', {id}, true)
        InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);
        $('#modalDeleteTransferWithLocation').modal('show');
    }
}

function validateOrder($button) {

    let route = Routing.generate('transfer_order_validate', {id});

    wrapLoadingOnActionButton($button, () => (
        $.post(route, function (response) {
            window.location.href = response.redirect;
            return true;
        })
    ));
}

$('.select2').select2();
