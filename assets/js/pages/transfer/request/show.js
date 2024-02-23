import Routing from '@app/fos-routing';

let tableArticle;


global.onReferenceChange = onReferenceChange;
global.validateTransfer = validateTransfer;
global.deleteRowTransfer = deleteRowTransfer;

$(function() {
    $('.select2').select2();

    const transferOriginId = $('#transfer-origin-id').val();
    const transferRequestId = $('#transferRequestId').val();
    Select2Old.articleReference($('#add-article-reference'), {
        locationFilter: transferOriginId,
    });

    tableArticle = initDataTable('tableArticle', {
        ajax: {
            "url": Routing.generate('transfer_request_article_api', {transfer: transferRequestId}, true),
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

    let modal = $("#modalAddArticle");
    let submit = $("#submitAddArticle");
    let url = Routing.generate('transfer_request_add_article', {transfer: transferRequestId});
    InitModal(modal, submit, url, {tables: [tableArticle]});

    let modalDeleteArticle = $("#modalDeleteArticle");
    let submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('transfer_request_remove_article', true);
    InitModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, {tables: [tableArticle]});

    let modalDeleteTransfer = $("#modalDeleteTransfer");
    let submitDeleteTransfer = $("#submitDeleteTransfer");
    let urlDeleteTransfer = Routing.generate('transfer_request_delete', true)
    InitModal(modalDeleteTransfer, submitDeleteTransfer, urlDeleteTransfer);
});

function onReferenceChange($select) {
    const transferRequestId = $('#transferRequestId').val();
    let reference = $select.val();
    if(!reference) {
        return;
    }

    let route = Routing.generate('transfer_request_add_article', {transfer: transferRequestId});
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
