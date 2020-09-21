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
            {"data": 'nature', 'name': 'nature', 'title': 'natures.nature', translated: true},
            {"data": 'code', 'name': 'code', 'title': 'Code'},
            {"data": 'quantity', 'name': 'code', 'title': 'acheminement.Quantité à acheminer', translated: true},
            {"data": 'lastMvtDate', 'name': 'lastMvtDate', 'title': 'Date dernier mouvement'},
            {"data": 'lastLocation', 'name': 'lastLocation', 'title': 'Dernier emplacement'},
            {"data": 'operator', 'name': 'operator', 'title': 'Opérateur'},
        ],
        order: [[2, 'asc']]
    });

    const $modalEditDispatch = $('#modalEditDispatch');
    const $submitEditDispatch = $('#submitEditDispatch');
    const urlDispatchEdit = Routing.generate('dispatch_edit', true);
    InitModal($modalEditDispatch, $submitEditDispatch, urlDispatchEdit);

    const $modalValidateDispatch = $('#modalValidateDispatch');
    const $submitTreatedDispatch = $modalValidateDispatch.find('.submit-button');
    const urlValidateDispatch = Routing.generate('dispatch_validate_request', {id: dispatchId}, true);
    InitModal($modalValidateDispatch, $submitTreatedDispatch, urlValidateDispatch, {tables: [packTable]});

    const $modalDeleteDispatch = $('#modalDeleteDispatch');
    const $submitDeleteDispatch = $('#submitDeleteDispatch');
    const urlDispatchDelete = Routing.generate('dispatch_delete', true);
    InitModal($modalDeleteDispatch, $submitDeleteDispatch, urlDispatchDelete);

    const $modalPack = $('#modalPack');
    const $submitNewPack = $modalPack.find('button.submit-new-pack');
    const $submitEditPack = $modalPack.find('button.submit-edit-pack');
    const urlNewPack = Routing.generate('dispatch_new_pack', {dispatch: dispatchId}, true);
    const urlEditPack = Routing.generate('dispatch_edit_pack', true);
    InitModal($modalPack, $submitNewPack, urlNewPack, {tables: [packTable]});
    InitModal($modalPack, $submitEditPack, urlEditPack, {tables: [packTable]});

    let modalDeletePack = $('#modalDeletePack');
    let submitDeletePack = $('#submitDeletePack');
    let urlDeletePack = Routing.generate('dispatch_delete_pack', true);
    InitModal(modalDeletePack, submitDeletePack, urlDeletePack, {tables: [packTable]});

    let $modalPrintDeliveryNote = $('#modalPrintDeliveryNote');
    let $submitPrintDeliveryNote = $modalPrintDeliveryNote.find('.submit');
    let urlPrintDeliveryNote = Routing.generate('print_delivery_note_dispatch', {dispatch: $('#dispatchId').val()}, true);
    InitModal($modalPrintDeliveryNote, $submitPrintDeliveryNote, urlPrintDeliveryNote);

    let $modalPrintWaybill = $('#modalPrintWaybill');
    let $submitPrintWayBill = $modalPrintWaybill.find('.submit');
    let urlPrintWaybill = Routing.generate('post_dispatch_waybill', {dispatch: $('#dispatchId').val()}, true);
    InitModal($modalPrintWaybill, $submitPrintWayBill, urlPrintWaybill);
});

function togglePackDetails(emptyDetails = false) {
    const $modal = $('#modalPack');
    const packCode = $modal.find('[name="pack"]').val();
    $modal.find('.pack-details').addClass('d-none');
    $modal.find('.spinner-border').removeClass('d-none');

    const $natureField = $modal.find('[name="nature"]');
    $natureField.val(null).trigger('change');
    const $quantityField = $modal.find('[name="quantity"]');
    $quantityField.val(null);
    const $packQuantityField = $modal.find('[name="pack-quantity"]');
    $packQuantityField.val(null);

    if (packCode && !emptyDetails) {
        $.get(Routing.generate('get_pack_intel', {packCode}))
            .then(({success, pack}) => {
                if (success && pack) {
                    if (pack.nature) {
                        $natureField.val(pack.nature.id).trigger('change');
                    }
                    if (pack.quantity || pack.quantity === 0) {
                        $quantityField.val(pack.quantity);
                        $packQuantityField.val(pack.quantity);
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

function openShowPackModal({code, nature, quantity, packQuantity, lastMovementDate, lastLocation, operator}) {
    const $modal = $('#modalShowPack');

    $modal.find('[name="pack-number"]').val(code);
    $modal.find('[name="pack-nature"]').val(nature);
    $modal.find('[name="pack-dispatch-quantity"]').val(quantity);
    $modal.find('[name="pack-quantity"]').val(packQuantity);
    $modal.find('[name="pack-last-movement"]').val(lastMovementDate);
    $modal.find('[name="pack-last-location"]').val(lastLocation);
    $modal.find('[name="pack-operator"]').val(operator);

    $modal.modal('show');
}

function openEditPackModal({packDispatchId, code, quantity, natureId, packQuantity}) {
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
    $modal.find('[name="pack"]').prop('disabled', true);
    $modal.find('button.submit-edit-pack').removeClass('d-none');

    const $natureField = $modal.find('[name="nature"]');
    const $quantityField = $modal.find('[name="quantity"]');
    const $packField = $modal.find('[name="pack"]');
    const $packQuantityField = $modal.find('[name="pack-quantity"]');

    $packField.val(code);
    $natureField.val(natureId);
    $quantityField.val(quantity);
    $packQuantityField.val(packQuantity);

    $modal.modal('show');
}

function openValidateDispatchModal() {
    const modalSelector = '#modalValidateDispatch'
    const $modal = $(modalSelector);

    clearModal(modalSelector);

    $modal.modal('show');
}

function runDispatchPrint() {
    const dispatchId = $('#dispatchId').val();
    $.get({
        url: Routing.generate('get_dispatch_packs_counter', {dispatch: dispatchId}),
    })
        .then(function ({packsCounter}) {
            if (!packsCounter) {
                alertErrorMsg('Vous ne pouvez pas imprimer un acheminement sans colis', true);
            } else {
                window.location.href = Routing.generate('print_dispatch_state_sheet', {dispatch: dispatchId});
            }
        })
}

function openDeliveryNoteModal($button) {
    const dispatchId = $button.data('dispatch-id');
    $
        .get(Routing.generate('api_delivery_note_dispatch', {dispatch: dispatchId}))
        .then((html) => {
            const $modal = $('#modalPrintDeliveryNote');
            const $modalBody = $modal.find('.modal-body');
            $modalBody.html(html);
            $modal.modal('show');
        })
}

function openWaybillModal($button) {
    const dispatchId = $button.data('dispatch-id');
    $
        .get(Routing.generate('api_dispatch_waybill', {dispatch: dispatchId}))
        .then((html) => {
            const $modal = $('#modalPrintWaybill');
            const $modalBody = $modal.find('.modal-body');
            $modalBody.html(html);
            $modal.modal('show');
        })
}

function copyTo($button, inputSourceName, inputTargetName) {
    const $modal = $button.closest('.modal');
    const $source = $modal.find(`[name="${inputSourceName}"]`);
    const $target = $modal.find(`[name="${inputTargetName}"]`);
    const valToCopy = $source.val();
    if ($target.is('textarea')) {
        $target.text(valToCopy);
    }
    else {
        $target.val(valToCopy);
    }
}

function reverseFields($button, inputName1, inputName2) {
    const $modal = $button.closest('.modal');
    const $field1 = $modal.find(`[name="${inputName1}"]`);
    const $field2 = $modal.find(`[name="${inputName2}"]`);
    const val1 = $field1.val();
    const val2 = $field2.val();
    $field1.val(val2);
    $field2.val(val1);
}
