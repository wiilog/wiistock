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
            {data: 'barCode', title: 'Code barre'},
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
    loadLogisticUnitPack(deliveryId);
});

function loadLogisticUnitPack(deliveryId) {
    const $logisticUnitsContainer = $('.logistic-units-container');
    wrapLoadingOnActionButton(
        $logisticUnitsContainer,
        () => (
            AJAX.route('POST', 'livraison_ul_api', {id: deliveryId})
                .json()
                .then(({html}) => {
                    $logisticUnitsContainer.html(html);
                    $logisticUnitsContainer.find('.articles-container table')
                        .each(function() {
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
                                    needsColor: true,
                                    dataToCheck: 'emergency',
                                    color: 'danger',
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
