let tableArticles;

$(document).ready(() => {

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
            {"data": 'barCode', 'title': 'Code barre'},
            {"data": 'Quantité', 'title': 'Quantité'}
        ],
    });

    let modal = $("#modalAddArticle");
    let submit = $("#submitAddArticle");
    let url = Routing.generate('transfer_request_add_article', {transfer: id});
    InitModal(modal, submit, url, {tables: [tableArticle]});

    let modalDeleteArticle = $("#modalDeleteArticle");
    let submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('transfer_request_remove_article', true);
    InitModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, {tables: [tableArticle]});

    let $modalEdit = $("#modalEditTransferRequest");
    let $submitEdit = $("#submitEditTransferRequest");
    let pathEdit = Routing.generate('transfer_request_edit', true);
    InitModal($modalEdit, $submitEdit, pathEdit);

    let modalDeleteTransfer = $("#modalDeleteTransfer");
    let submitDeleteTransfer = $("#submitDeleteTransfer");
    let urlDeleteTransfer = Routing.generate('transfer_request_delete', true)
    InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);
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
        if (response.success) {
           $("#add-article-code-selector").html(response.html || "");
            $('.error-msg').html('');
        } else {
            $('.error-msg').html(response.msg);
        }
    });
}

$('.select2').select2();

function validateTransfer(id, $button) {
    let route = Routing.generate('transfer_request_has_articles', {id});

    wrapLoadingOnActionButton($button, () => (
        $.post(route, function(response) {
            if (!response.success) {
                showBSAlert(response.msg, "danger", false);
                return false;
            } else {
                window.location.href = response.redirect;
                return true;
            }
        })
    ));
}

function deleteRowTransfer(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}
