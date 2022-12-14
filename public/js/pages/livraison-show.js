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

function endLivraison($button) {
    wrapLoadingOnActionButton(
        $button,
        () => (
            $.post({
                url: Routing.generate('livraison_finish', {id: getDeliveryId()})
            })
                .then(({success, redirect, message, tableArticlesNotRequestedData}) => {
                    if (success) {
                        window.location.href = redirect;
                    } else {
                        if (tableArticlesNotRequestedData) {
                            const modalArticlesNotRequested = $('#modal-articles-not-requested');
                            modalArticlesNotRequested.find('.table-articles-not-requested-container').empty().append('<table class="table w-100" id ="table-articles-not-requested"></table>');

                            let tableArticlesNotRequestedConfig = {
                                lengthMenu: [10, 25, 50],
                                columns: [
                                    {data: 'barCode', name: 'barCode', title: 'Code barre', className: 'barCode'},
                                    {data: 'label', name: 'label', title: 'Libellé'},
                                    {data: 'lu', name: 'lu', title: 'Unité logistique'},
                                    {data: 'location', name: 'location', title: 'Emplacement'},
                                ],
                                data: tableArticlesNotRequestedData,
                                order: [
                                    ['barCode', 'desc'],
                                    ['label', 'desc'],
                                ],
                                domConfig: {
                                    removeInfo: true,
                                    removeTableHeader: true,
                                },
                                rowConfig: {
                                    needsRowClickAction: true,
                                    needsColor: true,
                                    dataToCheck: 'urgence',
                                    color: 'danger',
                                },
                            };
                            initDataTable('table-articles-not-requested', tableArticlesNotRequestedConfig);

                            modalArticlesNotRequested.modal(`show`);

                            let $luSelect = $('#table-articles-not-requested tbody tr td select[name="logisticUnit"]');
                            $luSelect.on('change', function () {
                                let $select = $(this);
                                let $locationSelect = $select.closest('tr').find('td select[name="location"]');
                                if ($select.val()) {
                                    const luData = $select.select2('data')[0];
                                    let option = new Option(luData.lastLocation, luData.lastLocationId, true, true);
                                    $locationSelect.append(option);
                                    $locationSelect.attr('disabled', true);
                                } else {
                                    $locationSelect.attr('disabled', false);
                                    $locationSelect.empty();
                                }
                            });
                        } else if (message) {
                            showBSAlert(message, 'danger');
                        }
                    }
                    return success;
                })
        ),
        false);
}

async function treatArticlesNotRequested($button) {
    $button.pushLoader(`white`);
    const $modalArticlesNotRequested = $('#modal-articles-not-requested');
    const $tableRow = $modalArticlesNotRequested.find('tbody tr');
    let $locationSelect = $tableRow.find('td select[name="location"]');

    let locationSelectEmpty = [];
    $locationSelect.each(function () {
        if ($(this).val() == null) {
            locationSelectEmpty.push($(this));
        }
    });
    if (locationSelectEmpty.length > 0) {
        locationSelectEmpty.forEach(function (select) {
            $(select).addClass('is-invalid');
            $(select).closest('tr').find('td .select2-selection').addClass('is-invalid');
        });
        $button.popLoader();
    } else {
        let articles = [];
        await $tableRow.each(function () {
            let $row = $(this);
            let $luSelect = $row.find('td select[name="logisticUnit"]');
            let $locationSelect = $row.find('td select[name="location"]');
            articles.push({
                barCode: $row.find('td.barCode').text(),
                lu: $luSelect.val(),
                location: $locationSelect.val(),
            });
        });
        const deliveryId = await getDeliveryId();
        AJAX.route(
            'POST',
            'livraison_treat_articles_not_requested'
        ).json({
            deliveryId:  deliveryId,
            articles: articles,
        }).then(({success, message}) => {
            if (success) {
                endLivraison($button);
            } else {
                showBSAlert(message, 'danger');
                $button.popLoader();
            }
        });
        $button.popLoader();
    }
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
