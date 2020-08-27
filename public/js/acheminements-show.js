$(function () {
    const dispatchId = $('#dispatchId').val();

    let packTable = initDataTable('packTable', {
        ajax: {
            "url": Routing.generate('dispatch_pack_api', {dispatch: dispatchId}, true),
            "type": "GET"
        },
        rowConfig: {
            needsRowClickAction: true
        },
        domConfig: {
            removeInfo: true
        },
        columns: [
            {"data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'nature', 'name': 'nature', 'title': $('#natureTranslation').val()},
            {"data": 'code', 'name': 'code', 'title': 'Code'},
            {"data": 'quantity', 'name': 'code', 'title': 'Quantité'},
            {"data": 'lastMvtDate', 'name': 'lastMvtDate', 'title': 'Date dernier mouvement'},
            {"data": 'lastLocation', 'name': 'lastLocation', 'title': 'Dernier emplacement'},
            {"data": 'operator', 'name': 'operator', 'title': 'Opérateur'},
        ],
        order: [[2, 'asc']]
    });

    let modalModifyAcheminements = $('#modalEditAcheminements');
    let submitModifyAcheminements = $('#submitEditAcheminements');
    let urlModifyAcheminements = Routing.generate('acheminement_edit', true);
    InitialiserModal(modalModifyAcheminements, submitModifyAcheminements, urlModifyAcheminements);

    let modalDeleteAcheminements = $('#modalDeleteAcheminements');
    let submitDeleteAcheminements = $('#submitDeleteAcheminements');
    let urlDeleteAcheminements = Routing.generate('acheminement_delete', true);
    InitialiserModal(modalDeleteAcheminements, submitDeleteAcheminements, urlDeleteAcheminements);

    const $modalPack = $('#modalPack');
    const $submitNewPack = $modalPack.find('button.submit-new-pack');
    const $submitEditPack = $modalPack.find('button.submit-edit-pack');
    const urlNewPack = Routing.generate('dispatch_new_pack', {dispatch: dispatchId}, true);
    const urlEditPack = Routing.generate('dispatch_edit_pack', true);
    InitialiserModal($modalPack, $submitNewPack, urlNewPack, packTable, null, true, true, true);
    InitialiserModal($modalPack, $submitEditPack, urlEditPack, packTable, null, true, true, true);

});

function validateAcheminement(acheminementId, $button) {
    let params = JSON.stringify({id: acheminementId});

    wrapLoadingOnActionButton($button, () => (
        $.post({
            url: Routing.generate('demande_acheminement_has_packs'),
            data: params
        })
            .then(function (resp) {
                if (resp === true) {
                    return getCompareStock($button);
                } else {
                    $('#cannotValidate').click();
                    return false;
                }
            })
    ));
}

function togglePackDetails(emptyDetails = false) {
    const $modal = $('#modalPack');
    const packCode = $modal.find('[name="pack"]').val();
    $modal.find('.pack-details').addClass('d-none');
    $modal.find('.spinner-border').removeClass('d-none');

    const $natureField = $modal.find('[name="nature"]');
    $natureField.val(null).trigger('change');
    const $quantityField = $modal.find('[name="quantity"]');
    $quantityField.val(null);

    if (packCode && !emptyDetails) {
        $.get(Routing.generate('get_pack_intel', {packCode}))
            .then(({success, pack}) => {
                if (success && pack) {
                    if (pack.nature) {
                        $natureField.val(pack.nature.id).trigger('change');
                    }
                    if (pack.quantity || pack.quantity === 0) {
                        $quantityField.val(pack.quantity);
                    }
                }

                $modal.find('.pack-details').removeClass('d-none');
                $modal.find('.spinner-border').addClass('d-none');
            })
            .catch(() => {
                $modal.find('.pack-details').removeClass('d-none');
                $modal.find('.spinner-border').addClass('d-none');
            })
    }
    else {
        $modal.find('.spinner-border').addClass('d-none');
        if (packCode || emptyDetails) {
            $modal.find('.pack-details').removeClass('d-none');
        }
    }
}

function openNewPackModal() {
    const modalSelector = '#modalPack'
    const $modal = $(modalSelector);

    $modal.find('.packId').remove();
    $modal.find('.data').removeAttr('disabled');

    clearModal(modalSelector);
    togglePackDetails();

    // title
    $modal.find('.title-new-pack').removeClass('d-none');
    $modal.find('.title-edit-pack').addClass('d-none');

    // submit button
    $modal.find('button.submit-new-pack').removeClass('d-none');
    $modal.find('button.submit-edit-pack').addClass('d-none');

    $modal.modal('show');
}

function openEditPackModal({packDispatchId, code, quantity, natureId, treated}) {
    const modalSelector = '#modalPack'
    const $modal = $(modalSelector);

    clearModal(modalSelector);
    togglePackDetails(true);

    $modal.find('.data').removeAttr('disabled');

    // title
    $modal.find('.title-new-pack').addClass('d-none');
    $modal.find('.title-edit-pack').removeClass('d-none');

    $modal.find('.modal-body').append($('<input/>', {
        class: 'data',
        name: 'packDispatchId',
        value: packDispatchId,
        type: 'hidden'
    }));

    // new create button
    $modal.find('button.submit-new-pack').addClass('d-none');

    if (!treated) {
        $modal.find('[name="pack"]').prop('disabled', true);
        $modal.find('button.submit-edit-pack').removeClass('d-none');
    }
    else {
        $modal.find('.data').prop('disabled', true);
        $modal.find('button.submit-edit-pack').addClass('d-none');
    }

    const $natureField = $modal.find('[name="nature"]');
    const $quantityField = $modal.find('[name="quantity"]');
    const $packField = $modal.find('[name="pack"]');

    $packField.val(code);
    $natureField.val(natureId);
    $quantityField.val(quantity);

    $modal.modal('show');
}
