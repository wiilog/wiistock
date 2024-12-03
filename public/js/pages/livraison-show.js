$(function () {
    const deliveryId = getDeliveryId();
    let $modalDeleteDelivery = $('#modal-select-location');

    const $selectDeleteDeliveryLocation = $modalDeleteDelivery.find('select[name="location"]')
    Select2Old.location($selectDeleteDeliveryLocation);
    loadLogisticUnitPack(deliveryId);

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

    initDeliveryNoteModal();
});

function loadLogisticUnitPack(deliveryId) {
    const $logisticUnitsContainer = $('.logistic-units-container');
    const columns = $logisticUnitsContainer.data('initial-visible');

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
                            columns,
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

function initDeliveryNoteModal() {
    const $modal = $(`#modalPrintDeliveryNote`);
    const $modalBody = $modal.find(`.modal-body`);
    const deliveryOrderId = getDeliveryId();

    Form.create($modal)
        .submitTo(AJAX.POST, `delivery_note_delivery_order`, {
            success: ({attachmentId, headerDetailsConfig}) => {
                $(`.zone-entete`).html(headerDetailsConfig);

                Flash.presentFlashWhile(() => (
                    AJAX.route(AJAX.GET, `print_delivery_note_delivery_order`, {
                        deliveryOrder: deliveryOrderId,
                        attachment: attachmentId
                    }).file({
                        success: "Votre bon de livraison a bien été téléchargé.",
                        error: "Erreur lors du téléchargement du bon de livraison."
                    }).then(() => window.location.reload())
                ), `Le téléchargement du bon de livraison est en cours, veuillez patienter...`);
            }
        })
        .onOpen(() => {
            Modal.load(`api_delivery_note_livraison`, {deliveryOrder: deliveryOrderId, fromDelivery: true}, $modal, $modalBody, {
                onOpen: ({success, msg}) => {
                    if (success) {
                        $modal.find(`[name=buyer]`).on(`change`, function () {
                            const data = $(this).select2(`data`);
                            if (data.length > 0) {
                                const {fax, phoneNumber, address} = data[0];
                                const $modal = $(this).closest(`.modal`);
                                if (fax) {
                                    $modal.find(`input[name=buyerFax]`).val(fax);
                                }
                                if (phoneNumber) {
                                    $modal.find(`input[name=buyerPhone]`).val(phoneNumber);
                                }
                                if (address) {
                                    $modal.find(`[name=deliveryAddress],[name=invoiceTo],[name=soldTo],[name=endUser],[name=deliverTo]`).val(address);
                                }
                            }
                        });
                    } else {
                        Flash.add(Flash.ERROR, msg);
                    }
                },
                onClose: () => $modalBody.empty(),
            });
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

