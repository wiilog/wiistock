$(function () {
    const deliveryId = getDeliveryId();
    let $modalDeleteDelivery = $('#modal-select-location');

    const $selectDeleteDeliveryLocation = $modalDeleteDelivery.find('select[name="location"]')
    Select2Old.location($selectDeleteDeliveryLocation);
    loadLogisticUnitPack(deliveryId);

    let $modalPrintDeliveryNote = $('#modalPrintDeliveryNote');
    let $submitPrintDeliveryNote = $modalPrintDeliveryNote.find('.submit');
    let urlPrintDeliveryNote = Routing.generate('delivery_note_delivery_order', {deliveryOrder: getDeliveryId()}, true);
    InitModal($modalPrintDeliveryNote, $submitPrintDeliveryNote, urlPrintDeliveryNote, {
        success: ({attachmentId}) => {
            window.location.href = Routing.generate('print_delivery_note_delivery_order', {
                deliveryOrder: getDeliveryId(),
                attachment: attachmentId
            });
        }
    });

    let $modalPrintWaybill = $('#modalPrintWaybill');
    let $submitPrintWayBill = $modalPrintWaybill.find('.submit');
    let urlPrintWaybill = Routing.generate('post_delivery_waybill', {deliveryOrder: deliveryId}, true);
    InitModal($modalPrintWaybill, $submitPrintWayBill, urlPrintWaybill, {
        success: ({attachmentId, headerDetailsConfig}) => {
            $('.zone-entete').html(headerDetailsConfig);
            window.location.href = Routing.generate('print_waybill_delivery', {
                deliveryOrder: deliveryId,
                attachment: attachmentId,
            });
        },
    });
});

function loadLogisticUnitPack(deliveryId) {
    const $logisticUnitsContainer = $('.logistic-units-container');
    wrapLoadingOnActionButton($logisticUnitsContainer, () => (
            AJAX.route('GET', 'delivery_order_logistic_units_api', {id: deliveryId})
                .json()
                .then(({html}) => {
                    $logisticUnitsContainer.html(html);
                    $logisticUnitsContainer.find('.articles-container table')
                        .each(function () {
                            const $table = $(this);
                            initDataTable($table, {
                                serverSide: false,
                                ordering: true,
                                paging: false,
                                searching: false,
                                columns: [
                                    {data: 'Actions', title: '', className: 'noVis', orderable: false},
                                    {data: 'reference', title: 'Référence'},
                                    {data: 'barCode', title: 'Code barre'},
                                    {data: 'label', title: 'Libellé'},
                                    {data: 'quantity', title: 'Quantité'},
                                ],
                                domConfig: {
                                    removeInfo: true,
                                },
                                rowConfig: {
                                    needsRowClickAction: true,
                                },
                            })
                        });
                })
        )
    );
}

function endLivraison($button) {
    wrapLoadingOnActionButton(
        $button,
        () => (
            $.post({
                url: Routing.generate('livraison_finish', {id: getDeliveryId()})
            })
                .then(({success, redirect, message}) => {
                    if (success) {
                        window.location.href = redirect;
                    } else {
                        showBSAlert(message, 'danger');
                    }

                    return success;
                })
        ),
        false);
}

function askForDeleteDelivery() {
    clearDeleteDeliveryModal();
    let $modalDeleteDelivery = $('#modal-select-location');
    $modalDeleteDelivery.modal('show');

    const $locationSelect = $modalDeleteDelivery.find('select[name="location"]')
    const $submitButtonDeleteDelivery = $modalDeleteDelivery.find('button[type="submit"]');

    $submitButtonDeleteDelivery.off('click');
    $submitButtonDeleteDelivery.on('click', function () {
        const value = $locationSelect.val();
        if (value) {
            wrapLoadingOnActionButton(
                $submitButtonDeleteDelivery,
                () => (
                    $
                        .ajax({
                            type: 'DELETE',
                            url: Routing.generate('livraison_delete', {'livraison': getDeliveryId()}, true),
                            data: {
                                dropLocation: value
                            }
                        })
                        .then(({success, redirect, message}) => {
                            if (success) {
                                window.location.href = redirect;
                            } else {
                                showBSAlert(message, 'danger');
                            }

                            return success;
                        })
                ),
                false
            );
        } else {
            showBSAlert('Veuillez sélectionner un emplacement.', 'danger');
        }
    })
}

function clearDeleteDeliveryModal() {
    let $modalDeleteDelivery = $('#modal-select-location');
    const $locationSelect = $modalDeleteDelivery.find('select[name="location"]')
    $locationSelect.html('');
    $locationSelect.val('');
}

function getDeliveryId() {
    return $('input[type="hidden"][name="delivery-id"]').val();
}

function openDeliveryNoteModal($button, fromDelivery = false) {
    const livraisonId = getDeliveryId();
    $.get(Routing.generate('api_delivery_note_livraison', {deliveryOrder: livraisonId, fromDelivery}))
        .then((result) => {
            if(result.success) {
                const $modal = $('#modalPrintDeliveryNote');
                const $modalBody = $modal.find('.modal-body');
                $modalBody.html(result.html);
                $modal.modal('show');

                $('select[name=buyer]').on('change', function (){
                    const data = $(this).select2('data');
                    if(data.length > 0){
                        const {fax, phoneNumber, address} = data[0];
                        const $modal = $(this).closest('.modal');
                        if(fax){
                            $modal.find('input[name=buyerFax]').val(fax);
                        }
                        if(phoneNumber){
                            $modal.find('input[name=buyerPhone]').val(phoneNumber);
                        }
                        if(address){
                            $modal.find('[name=deliveryAddress],[name=invoiceTo],[name=soldTo],[name=endUser],[name=deliverTo] ').val(address);
                        }
                    }
                });
            } else {
                showBSAlert(result.msg, "danger");
            }
        });
}

function openWaybillModal($button) {
    const livraisonId = getDeliveryId();

    Promise.all([
        $.get(Routing.generate('check_delivery_waybill', {deliveryOrder: livraisonId})),
        $.get(Routing.generate('api_delivery_waybill', {deliveryOrder: livraisonId})),
    ]).then((values) => {
        let check = values[0];
        if(!check.success) {
            showBSAlert(check.msg, "danger");
            return;
        }

        let result = values[1];
        if(result.success) {
            const $modal = $('#modalPrintWaybill');
            const $modalBody = $modal.find('.modal-body');
            $modalBody.html(result.html);
            $modal.modal('show');

            $('select[name=receiverUsername]').on('change', function (){
                const data = $(this).select2('data');
                if(data.length > 0){
                    const {email, phoneNumber, address} = data[0];
                    const $modal = $(this).closest('.modal');
                    if(phoneNumber || email){
                        $modal.find('input[name=receiverEmail]').val(phoneNumber.concat(' - ', email));
                    }
                    if(address){
                        $modal.find('[name=receiver]').val(address);
                    }
                }
            });
        } else {
            showBSAlert(result.msg, "danger");
        }
    });
}

function copyTo($button, inputSourceName, inputTargetName) {
    const $modal = $button.closest('.modal');
    const $source = $modal.find(`[name="${inputSourceName}"]`);
    const $target = $modal.find(`[name="${inputTargetName}"]`);
    const valToCopy = $source.val();
    if($target.is('textarea')) {
        $target.text(valToCopy);
    } else {
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
