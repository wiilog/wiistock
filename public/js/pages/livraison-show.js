$(function () {
    const deliveryId = getDeliveryId();
    let $modalDeleteDelivery = $('#modal-select-location');

    const $selectDeleteDeliveryLocation = $modalDeleteDelivery.find('select[name="location"]')
    Select2Old.location($selectDeleteDeliveryLocation);

    let pathArticle = Routing.generate('livraison_article_api', {id: deliveryId});
    let tableArticleConfig = {
        ajax: {
            'url': pathArticle,
            "type": "POST"
        },
        columns: [
            {data: 'Actions', title: '', className: 'noVis', orderable: false},
            {data: 'reference', title: 'Référence'},
            {data: 'label', title: 'Libellé'},
            {data: 'location', title: 'Emplacement'},
            {data: 'quantity', title: 'Quantité'},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        order: [['reference', "asc"]]
    };
    initDataTable('tableArticle_id', tableArticleConfig);

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
});

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
                    }
                    else {
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
                            }
                            else {
                                showBSAlert(message, 'danger');
                            }

                            return success;
                        })
                ),
                false
            );
        }
        else {
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

function openDeliveryNoteModal($button) {
    const livraisonId = getDeliveryId();
    $.get(Routing.generate('api_delivery_note_livraison', {deliveryOrder: livraisonId}))
        .then((result) => {
            if(result.success) {
                const $modal = $('#modalPrintDeliveryNote');
                const $modalBody = $modal.find('.modal-body');
                $modalBody.html(result.html);
                $modal.modal('show');

                $('select[name=buyer]').on('change', function (){
                    const data = $(this).select2('data');
                    if(data.length > 0 && data[0].email){
                        const {fax, phoneNumber, address} = data[0];
                        const $modal = $(this).closest('.modal');
                        $modal.find('input[name=buyerPhone]').val(phoneNumber);
                        $modal.find('input[name=buyerFax]').val(fax);
                        $modal.find('[name=deliveryAddress],[name=invoiceTo],[name=soldTo],[name=endUser],[name=deliverTo] ').val(address);
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
