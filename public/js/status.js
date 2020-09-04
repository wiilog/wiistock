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
            {"data": 'actions', "name": 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'category', "name": 'category', 'title': 'Entité'},
            {"data": 'label', "name": 'label', 'title': 'Libellé'},
            {"data": 'comment', "name": 'comment', 'title': 'Commentaire'},
            {"data": 'defaultStatus', "name": 'defaultStatus', 'title': 'Statut par défaut'},
            {"data": 'treatedStatus', "name": 'treatedStatus', 'title': 'Statut traité'},
            {"data": 'notifToDeclarant', "name": 'notifToDeclarant', 'title': 'Envoi de mails au déclarant'},
            {"data": 'order', "name": 'order', 'title': 'Ordre'},
        ],
        order: [
            [1, 'asc'],
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

function hideOptionOnChange($modal, forceClear = true) {
    const $select = $modal.find('[name="category"]');
    const $dispatchFields = $modal.find('.dispatch-fields');
    const $disputeFields = $modal.find('.dispute-fields');

    $dispatchFields.addClass('d-none');
    $disputeFields.addClass('d-none');
    $modal.find('.field-needed').removeClass('needed');

    if (forceClear) {
        $dispatchFields.find('select').find('option:selected').prop("selected", false);
        $dispatchFields.find('select').val('');

        $disputeFields.find('select').find('option:selected').prop("selected", false);
        $disputeFields.find('select').val('');
    }

    const category = Number($select.val());
    if (category) {
        const categoryStatusDispatchId = Number($('#categoryStatusDispatchId').val());
        const $fields = (category === categoryStatusDispatchId) ? $dispatchFields : $disputeFields;
        $fields.removeClass('d-none');
        $fields.find('.field-needed').addClass('needed');
    }
}
