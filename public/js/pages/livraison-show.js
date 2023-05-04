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
        success: ({attachmentId, headerDetailsConfig}) => {
            $('.zone-entete').html(headerDetailsConfig);
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
                                    {data: 'project', title: Translation.of('Ordre', 'Livraison', 'Détails', 'Projet')},
                                    {data: 'comment', title: 'Commentaire', orderable: false},
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
                .then(async ({success, redirect, message, tableArticlesNotRequestedDataBylu}) => {
                    if (success) {
                        window.location.href = redirect;
                    } else {
                        if (tableArticlesNotRequestedDataBylu) {
                            const modalArticlesNotRequested = $('#modal-articles-not-requested');
                            const $ulContainer = modalArticlesNotRequested.find('.modal-body .ul-container');
                            $ulContainer.empty();
                            await Object.entries(tableArticlesNotRequestedDataBylu).forEach((data) => {
                                let lu = data[0];
                                let tableArticlesNotRequestedData = data[1];
                                $ulContainer.append(`
                                    <div class="wii-section-title my-4"> Article(s) à enlever de l’unité logistique ${lu}</div>
                                    <div class="dataTable"><table class="table w-100" id ="table-articles-not-requested-${lu}"></table></div>
                                `);
                                let tableArticlesNotRequestedConfig = {
                                    paging: false,
                                    destroy: true,
                                    columns: [
                                        {data: 'barCode', name: 'barCode', title: 'Code barre', className: 'barCode', orderable: false},
                                        {data: 'label', name: 'label', title: 'Libellé', orderable: false},
                                        {data: 'lu', name: 'lu', title: 'Unité logistique', orderable: false},
                                        {data: 'location', name: 'location', title: 'Emplacement*', orderable: false},
                                    ],
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
                                    },
                                    data: tableArticlesNotRequestedData,
                                };
                                initDataTable(`table-articles-not-requested-${lu}`, tableArticlesNotRequestedConfig);
                            });
                            let $luSelect = $('tbody tr td select[name="logisticUnit"]');
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
                            modalArticlesNotRequested.modal(`show`);
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
            $(select).closest('td').find('.select2-selection').addClass('is-invalid');
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
