let tableArticles;
const $addArticleModal = $("#modalNewArticle");

$(document).ready(() => {
    $('form[name="transfer_request"]').submit(function(e) {
        submitTransferRequest.call(this, e,  Routing.generate("transfer_request_edit", {transfer: id}))
    });

    Select2.articleReference($(".ajax-autocomplete"));

    let modal = $("#modalAddArticle");
    let submit = $("#submitAddArticle");
    let url = Routing.generate('transfer_request_add_article', {transfer: id});
    InitModal(modal, submit, url, {tables: [tableArticle]});

    let modalDeleteArticle = $("#modalDeleteArticle");
    let submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('transfer_request_remove_article', true);
    InitModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, {tables: [tableArticle]});

    let modalDeleteTransfer = $("#modalDeleteTransfer");
    let submitDeleteTransfer = $("#submitDeleteTransfer");
    let urlDeleteTransfer = Routing.generate('transfer_request_delete', true)
    InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);

    tableArticle = initDataTable('tableArticle', {
        ajax: {
            "url": Routing.generate('transfer_request_article_api', {transfer: id}, true),
            "type": "POST"
        },
        order: [[1, 'desc']],
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'Libellé', 'title': 'Libellé'},
            {"data": 'Quantité', 'title': 'Quantité'}
        ],
    });
});

function onReferenceChange($select) {
    let reference = $select.val();
    if(!reference) {
        return;
    }

    let route = Routing.generate('transfer_request_add_article', {transfer: id});
    let data = JSON.stringify({
        fetchOnly: true,
        reference
    });

    $.post(route, data, function(response) {
        console.log(response);
        if(response.success && response.html) {
            $("#add-article-code-selector").html(response.html);
        } else {
            $("#add-article-code-selector").html("");
        }
    });
}

$('.select2').select2();

function validateTransfer() {
    //TODO: validate transfer and create order
}

function deleteRowTransfer(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}
