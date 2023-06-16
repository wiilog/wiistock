window.addEventListener( "pageshow", function ( event ) {
    const historyTraversal = event.persisted ||
        ( typeof window.performance != "undefined" &&
            window.performance.navigation.type === 2 );
    if ( historyTraversal ) {
        window.location.reload();
    }
});

$(document).ready(() => {

    const $addOrCreate = $(`[name="addOrCreate"]`).closest(`.wii-radio-container`);

    const $purchaseRequestInfosTemplate = $(`#purchase-request-infos-template`);
    const $emptyCart = $(`.empty-cart`);

    const $existingDelivery = $(`.existing-delivery`);
    const $existingCollect = $(`.existing-collect`);
    const $existingPurchase = $(`.existing-purchase`);

    const $createDelivery = $(`.create-delivery`);
    const $createCollect = $(`.create-collect`);
    const $createPurchase = $(`.create-purchase`);

    const $requestTypeRadio = $(`[name="requestType"]`);

    const requestType = $requestTypeRadio.val();
    const $cartContentContainers = $('.wii-form > .cart-content');
    const $cartContentToShow = $cartContentContainers.filter(`[data-request-type="${requestType}"]`);
    if (containsLogisticUnits($cartContentToShow)) {
        loadLogisticUnitsCartForm($requestTypeRadio, $addOrCreate, $existingPurchase);
    }
    else {
        handleRequestTypeChange($requestTypeRadio, $addOrCreate, $existingPurchase);
        $requestTypeRadio.on(`change`, function () {
            handleRequestTypeChange($(this), $addOrCreate, $existingPurchase);
        });
    }

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
        const requestType = $('input[name="requestType"]:checked').val();

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
            toggleSelectedPurchaseRequest($existingPurchase, requestType);

            initializePurchaseRequestInfos($purchaseInfos, id);
        });
    });

    $(document).on(`click`, `.remove-reference`, function() {
        const $currentButton = $(this);
        const route = Routing.generate(`cart_remove_reference`, {
            reference: $currentButton.data(`id`)
        });

        $.post(route, response => {
            $(`.remove-reference[data-id="${$currentButton.data(`id`)}"]`).each(function() {
                const $button = $(this);
                const $cartReferenceContainer = $button.closest(`.cart-reference-container`);
                const $wiiBox = $cartReferenceContainer.closest('.wii-box');

                $cartReferenceContainer.remove();

                if ($wiiBox.exists() && !containsReferences($wiiBox)) {
                    const $wiiBoxContainer = $wiiBox.parent();
                    $wiiBox.remove();
                    const $remainingWiiBoxes = $wiiBoxContainer.find('.wii-box');
                    if ($remainingWiiBoxes.length === 0) {
                        const $cartContentContainers = $('.wii-form > .cart-content');
                        $cartContentContainers.addClass('d-none');
                        $emptyCart.removeClass('d-none');
                    }
                }
            });

            $(`.header-icon.cart`).find(`.icon-figure`).text(response.count)[response.count ? `addClass` : `removeClass`](`d-none`);
            showBSAlert(response.msg, `success`);
        });
    });

    Form.create(`.wii-form`).onSubmit(data => {
        const url = Routing.generate('cart_validate', true);
        const params = JSON.stringify(data.asObject());
        wrapLoadingOnActionButton($('.cart-content').find('button[type=submit]'), () => (
            $.post(url, params, function (response) {
                showBSAlert(response.msg, response.success ? 'success' : 'danger');
                if (response.success && response.link) {
                    window.location.href = response.link;
                }
            })
        ));
    });
});

function initializePurchaseRequestInfos($purchaseInfos, id) {
    initDataTable($purchaseInfos.find(`table`), {
        destroy: true,
        processing: true,
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
        info: false,
        drawConfig: {
            needsPagingHide: true,
        },
    });
}

function cartTypeChange($type) {
    onTypeChange($type);

    if($type.attr(`name`) === `deliveryType`) {
        const defaultDestinations = JSON.parse($(`#default-delivery-locations`).val());
        const type = $type.val();
        const $destination = $type.closest(`.wii-form`).find(`select[name=location]`);
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
            const $subForm = $select.closest('.sub-form');
            $subForm.find('.delivery-comment').html(data.comment);
            $subForm.find('.request-free-fields-section .free-fields-container').html(data.freeFields);
        });

        let pathReferences = Routing.generate("demande_api_references", true);
        let tableDeliveryReferencesConfig = {
            destroy: true,
            processing: true,
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
            info: false,
            drawConfig: {
                needsPagingHide: true,
            },
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
            const $subForm = $select.closest('.sub-form');
            $subForm.find('.collect-object').text(data.object);
            $subForm.find('.collect-destination').text(data.destination);
            $subForm.find('.collect-comment').html(data.comment);
            $subForm.find('.request-free-fields-section .free-fields-container').html(data.freeFields);
        });

        let pathReferences = Routing.generate("collecte_api_references", true);
        let tableCollectReferencesConfig = {
            destroy: true,
            processing: true,
            paging: true,
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
            info: false,
            drawConfig: {
                needsPagingHide: true,
            },
        }
        let tableCollectReferences = initDataTable('tableCollectReferences', tableCollectReferencesConfig);
        $('.collect-request-content').removeClass("d-none");
    } else {
        $('.collect-request-content').addClass("d-none");
    }
}
function handleRequestTypeChange($requestType, $addOrCreate, $existingPurchase) {
    const $cartContentContainers = $('.wii-form > .cart-content');
    $cartContentContainers.addClass('d-none');

    $cartContentContainers
        .find('.data')
        .addClass("data-save")
        .removeClass('data');

    $(`.sub-form`).addClass(`d-none`);
    $('input[name="addOrCreate"]').prop(`checked`, false);

    const requestType = $requestType.val();

    const $cartContentToShow = $cartContentContainers.filter(`[data-request-type="${requestType}"]`);

    if (containsReferences($cartContentToShow)) {
        $cartContentToShow.removeClass('d-none');

        $cartContentToShow
            .find('.data-save')
            .removeClass("data-save")
            .addClass('data');

        $('.cart-content[data-request-type=purchase] input[type="number"]').each(function(){
            $(this).prop('required', !$(this).is(':disabled'));
        })

        if (requestType === "delivery" || requestType === "collect") {
            $addOrCreate.removeClass('d-none');
        } else if (requestType === "purchase") {
            $addOrCreate.addClass('d-none');
        }

        $('.target-location-picking-container').toggleClass('d-none', requestType !== "delivery")

        toggleSelectedPurchaseRequest($existingPurchase, requestType);
    }
}

function loadLogisticUnitsCartForm($requestType, $addOrCreate, $existingPurchase) {
    const $cartContentContainers = $('.wii-form > .cart-content');
    const requestType = $requestType.val();

    const $cartContentToShow = $cartContentContainers.filter(`[data-request-type="${requestType}"]`);

    if (containsLogisticUnits($cartContentToShow)) {
        $cartContentToShow.removeClass('d-none');
        $cartContentToShow
            .find('.data-save')
            .removeClass("data-save")
            .addClass('data');

        $('.cart-content[data-request-type=purchase] input[type="number"]').each(function(){
            $(this).prop('required', !$(this).is(':disabled'));
        })

        if (requestType === "delivery" || requestType === "collect") {
            $addOrCreate.removeClass('d-none');
        } else if (requestType === "purchase") {
            $addOrCreate.addClass('d-none');
        }

        $('.target-location-picking-container').toggleClass('d-none', requestType !== "delivery")

        toggleSelectedPurchaseRequest($existingPurchase, requestType);
        loadLogisticUnitPack();
    }
}

function toggleSelectedPurchaseRequest($existingPurchase, requestType) {
    $existingPurchase.toggleClass(`d-none`, (requestType !== "purchase") || ($('.selected-purchase-requests').children().length === 0));
}

function containsReferences($container) {
    const $cartReferenceContainers = $container.find(`.cart-reference-container`);
    return ($cartReferenceContainers.length > 0);
}

function containsLogisticUnits($container) {
    const $numberUL = $container.find(`input[name=articlesInCart]`).val();
    return ($numberUL > 0);
}


function loadLogisticUnitPack() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    wrapLoadingOnActionButton(
        $logisticUnitsContainer,
        () => (
            AJAX.route('POST', 'articles_logistic_units_api', {})
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
                                    { data: 'actions', title: '', class:'noVis hideOrder sorting_disabled', orderable: false },
                                    { data: 'reference', title: 'Référence'},
                                    { data: 'barCode', title: 'Code barre'},
                                    { data: 'label', title: 'Libellé'},
                                    { data: 'batch', title: 'Lot'},
                                    { data: 'quantity', title: 'Quantité'},
                                ],
                                domConfig: {
                                    removeInfo: true,
                                    needsPaginationRemoval: true,
                                    removeLength: true,
                                    removeTableHeader: true,
                                },
                            })
                        });
                })
        )
    )
}

function removeLogisticUnitRow(id, type) {
    AJAX.route('POST', 'articles_remove_row_cart_api', {id, type})
        .json()
        .then(({success, emptyCart}) => {
            if (success) {
                if (emptyCart) {
                    location.reload();
                }
                else {
                    loadLogisticUnitPack();
                }
            }
        })
}

function clearLogisticUnitsContainer() {
    const $logisticUnitsContainer = $('.logistic-units-container');
    $logisticUnitsContainer.empty();
}
