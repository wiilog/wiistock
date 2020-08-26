$(function () {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_STATUS);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    let pathStatus = Routing.generate('status_param_api', true);
    let tableStatusConfig = {
        processing: true,
        serverSide: true,
        ajax: {
            "url": pathStatus,
            "type": "POST"
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Category', 'title': 'Entité'},
            {"data": 'Label', 'title': 'Libellé'},
            {"data": 'Comment', 'title': 'Commentaire'},
            {"data": 'Treated', 'title': 'Statut traité'},
            {"data": 'NotifToDeclarant', 'title': 'Envoi de mails au déclarant'},
            {"data": 'Order', 'title': 'Ordre'},
        ],
        order: [
            [5, 'asc']
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    let tableStatus = initDataTable('tableStatus', tableStatusConfig);

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

function hideOptionOnChange($select, $modal) {
    const $category = $select.find('option:selected').text();
    const $sendMailBuyer =  $modal.find('.send-mail-user');
    const $sendMailRecipient = $modal.find('.send-mail-recipient');
    const $disputeComment = $modal.find('.dispute-comment');
    const $typesLabel = $modal.find('.types-label');
    const $acheminementTrans =  $modal.find('#acheminementTranslation').val();

    if ($category === $acheminementTrans) {
        $sendMailBuyer.addClass('d-none');
        $disputeComment.addClass('d-none');
        $sendMailRecipient.removeClass('d-none');
        $typesLabel.removeClass('d-none');
        $typesLabel.find('select').addClass('needed');
    }
    else {
        $sendMailBuyer.removeClass('d-none');
        $disputeComment.removeClass('d-none');
        $typesLabel.addClass('d-none');
        $sendMailRecipient.addClass('d-none');
        $typesLabel.find('select').removeClass('needed');
        $typesLabel.find('select').find('option:selected').prop("selected", false);
        $typesLabel.find('select').val('');
    }
}
