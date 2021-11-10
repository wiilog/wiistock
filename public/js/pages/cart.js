window.addEventListener( "pageshow", function ( event ) {
    const historyTraversal = event.persisted ||
        ( typeof window.performance != "undefined" &&
            window.performance.navigation.type === 2 );
    if ( historyTraversal ) {
        window.location.reload();
    }
});

$(document).ready(() => {
    $(document).on(`click`, `.remove-reference`, function() {
        const $button = $(this);
        const route = Routing.generate(`cart_remove_reference`, {
            reference: $button.data(`id`)
        });

        $.post(route, response => {
            $button.closest(`.cart-reference-container`).remove();
            $(`.header-icon.cart`).find(`.icon-figure`).text(response.count)[response.count ? `addClass` : `removeClass`](`d-none`);
            showBSAlert(response.msg, `success`);
        });
    })

    const $addOrCreate = $(`[name="addOrCreate"]`).closest(`.wii-radio-container`);

    const $purchaseRequestInfosTemplate = $(`#purchase-request-infos-template`);
    const $allReferences = $(`.all-references`);
    const $referencesByBuyer = $(`.references-by-buyer`);

    const $existingDelivery = $(`.existing-delivery`);
    const $existingCollect = $(`.existing-collect`);
    const $existingPurchase = $(`.existing-purchase`);

    const $createDelivery = $(`.create-delivery`);
    const $createCollect = $(`.create-collect`);
    const $createPurchase = $(`.create-purchase`);

    $(`[name="requestType"]`).on(`change`, function() {
        $allReferences.hide();
        $referencesByBuyer.hide();

        $addOrCreate.show();
        $(`.sub-form`).addClass(`d-none`);
        $('input[name="addOrCreate"]').prop(`checked`, false);

        const requestType = $(this).val();
        if(requestType === "delivery"){
            $allReferences.show();
            $(`.quantity-label`).text(`Quantité à livrer`);
        } else if(requestType === "collect") {
            $allReferences.show();
            $(`.quantity-label`).text(`Quantité à collecter`);
        } else if(requestType === "purchase") {
            $referencesByBuyer.show();
            $existingPurchase.removeClass(`d-none`);
            $addOrCreate.hide();
            $(`.quantity-label`).text(`Quantité demandée`);
        }
    });

    $(`input[name="addOrCreate"][value="add"]`).on(`click`, function() {
        $(`.sub-form`).addClass(`d-none`);

        const requestType = $('input[name="requestType"]:checked').val();
        if(requestType === "delivery"){
            $('select[name="delivery-request"]').val("-").trigger('change');
            $existingDelivery.removeClass('d-none');
        } else if(requestType === "collect") {
            $('select[name="collect-request"]').val("-").trigger('change');
            $existingCollect.removeClass('d-none');
        } else if(requestType === "purchase") {
            $existingPurchase.removeClass('d-none');
        }
    })

    $(`input[name="addOrCreate"][value="create"]`).on(`click`, function() {
        $(`.sub-form`).addClass(`d-none`);

        const requestType = $('input[name="requestType"]:checked').val();
        if(requestType === "delivery"){
            $('select[name="delivery-request"]').val("-").trigger('change');
            $createDelivery.removeClass('d-none');
        } else if(requestType === "collect") {
            $('select[name="collect-request"]').val("-").trigger('change');
            $createCollect.removeClass('d-none');
        } else if(requestType === "purchase") {
            $createPurchase.removeClass('d-none');
        }
    });

    $(`select[name="existingPurchase"]`).on(`change`, function() {
        $(`.purchase-references`).remove();

        $(`select[name="existingPurchase"]`).each(function() {
            const $option = $(this).find(`option:not([disabled], [readonly]):selected`);
            const id = $option.data(`value`);
            const number = $option.data(`number`);
            const requester = $option.data(`requester`);

            if(!id || $(`.purchase-references[data-id="${id}"]`).exists()) {
                return;
            }

            const $purchaseInfos = $($purchaseRequestInfosTemplate.html());
            $purchaseInfos.attr(`data-number`, number)
                .find(`.purchase-request-infos`)
                .text(`${number} - ${requester}`);
            $(`.selected-purchase-requests`).append($purchaseInfos);

            initializePurchaseRequestInfos($purchaseInfos, id);
        });
    });

    Form.create(`.wii-form`).onSubmit(data => {
        const url = Routing.generate('cart_validate', true);
        console.log(data.asObject());
        const params = JSON.stringify(data.asObject());
        $.post(url, params, function (response) {
            showBSAlert(response.msg, response.success ? 'success' : 'danger');
            if (response.success) {
                window.location.href = response.link;
            }
        });
    });
});

function initializePurchaseRequestInfos($purchaseInfos, id) {
    initDataTable($purchaseInfos.find(`table`), {
        destroy: true,
        serverSide: true,
        processing: true,
        paging: false,
        ajax: {
            url: Routing.generate("purchase_api_references", true),
            type: "POST",
            data: {
                purchaseId: () => id,
            }
        },
        columns: [
            {"data": "reference", "title": "Référence"},
            {"data": "libelle", "title": "Libellé"},
            {"data": "quantity", "title": "Quantité"},
        ],
        filter: false,
        ordering: false,
        info: false

    });
}

function cartTypeChange($type) {
    onTypeChange($type);

    if($type.attr(`name`) === `deliveryType`) {
        const defaultDestinations = JSON.parse($(`#default-delivery-locations`).val());
        const type = $type.val();
        const $destination = $type.closest(`.wii-form`).find(`select[name=destination]`);
        const defaultDestination = defaultDestinations[type] || defaultDestinations['all'];

        $destination.attr(`disabled`, !type);
        $destination.val(null)
        if(defaultDestination) {
            $destination.append(new Option(defaultDestination.label, defaultDestination.id, true, true))
        }

        $destination.trigger(`change`);
    } else if($type.attr(`name`) === `collectType`) {
        const $destination = $type.closest(`.wii-form`).find(`select[name=location]`);
        $destination.val(null).trigger(`change`);
        $destination.attr(`disabled`, !$type.val());
    }
}

function onDeliveryChanged($select) {
    const val = $select.val();

    if($select.val() !== "-") {
        $.get(Routing.generate(`cart_delivery_data`, {request: val}), function(data) {
            $('.delivery-comment').html(data.comment);
        })

        let pathReferences = Routing.generate("demande_api_references", true);
        let tableDeliveryReferencesConfig = {
            destroy: true,
            serverSide: true,
            processing: true,
            paging: false,
            ajax: {
                url: pathReferences,
                type: "POST",
                data: {
                    deliveryId: () => $select.val(),
                }
            },
            columns: [
                {"data": "reference", "title": "Référence"},
                {"data": "libelle", "title": "Libellé"},
                {"data": "quantity", "title": "Quantité"},
            ],
            filter: false,
            ordering: false,
            info: false

        }
        let tableDeliveryReferences = initDataTable('tableDeliveryReferences', tableDeliveryReferencesConfig);
        $('.delivery-request-content').removeClass("d-none");
    } else {
        $('.delivery-request-content').addClass("d-none");
    }
}

function onCollectChanged($select) {
    const val = $select.val();

    if($select.val() !== "-"){
        $.get(Routing.generate(`cart_collect_data`, {request: val}), function(data) {
            $('.collect-object').text(data.object);
            $('.collect-destination').text(data.destination);
            $('.collect-comment').html(data.comment);
        })

        let pathReferences = Routing.generate("collecte_api_references", true);
        let tableCollectReferencesConfig = {
            destroy: true,
            serverSide: true,
            processing: true,
            paging: false,
            ajax: {
                url: pathReferences,
                type: "POST",
                data: {
                    collectId: () => $select.val(),
                }
            },
            columns: [
                {"data": "reference", "title": "Référence"},
                {"data": "libelle", "title": "Libellé"},
                {"data": "quantity", "title": "Quantité"},
            ],
            filter: false,
            ordering: false,
            info: false

        }
        let tableCollectReferences = initDataTable('tableCollectReferences', tableCollectReferencesConfig);
        $('.collect-request-content').removeClass("d-none");
    } else {
        $('.collect-request-content').addClass("d-none");
    }
}
