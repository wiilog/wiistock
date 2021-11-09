let $modalAddToRequest = null;

$(document).ready(() => {
    $modalAddToRequest = $('#modalAddToRequest');

    $modalAddToRequest.on('hide.bs.modal', function() {
        clearModal($modalAddToRequest);
        $modalAddToRequest.find('.type-body').html('');
        $('#submitAddToRequest').addClass('d-none');
    });

    let url = Routing.generate('cart_add_to_request', true);

    InitModal($modalAddToRequest, $('#submitAddToRequest'), url);

    const table = initDataTable(`cartTable`, {
        responsive: true,
        serverSide: true,
        processing: true,
        searching: false,
        order: [[`label`, `desc`]],
        ajax: {
            url: Routing.generate(`cart_api`, true),
            type: `POST`,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {data: `actions`, name: `actions`, title: ``, className: 'noVis', orderable: false, width: `10px`},
            {data: `label`, name: `label`, title: `Libellé`},
            {data: `reference`, name: `reference`, title: `Référence`},
            {data: `supplierReference`, name: `supplierReference`, title: `Référence fournisseur`, orderable: false,},
            {data: `type`, name: `type`, title: `Type`},
            {data: `availableQuantity`, name: `availableQuantity`, title: `Quantité disponible`},
        ],
    });

    $(document).on(`click`, `.remove-reference`, function() {
        const route = Routing.generate(`cart_remove_reference`, {
            reference: $(this).data(`id`)
        });

        $.post(route, response => {
            $(`.header-icon.cart`).find(`.icon-figure`).text(response.count)[response.count ? `addClass` : `removeClass`](`d-none`);
            table.ajax.reload();
            showBSAlert(response.msg, `success`);
        });
    })

    const refsCount = $('#cart-refs-count').val();
    if (refsCount && refsCount> 0) {
        $('.add-cart-to-request').removeClass('d-none');
    }

    const $existingDelivery = $(`.existing-delivery`);
    const $existingCollect = $(`.existing-collect`);
    const $existingPurchase = $(`.existing-purchase`);

    const $createDelivery = $(`.create-delivery`);
    const $createCollect = $(`.create-collect`);
    const $createPurchase = $(`.create-purchase`);

    $('.request-type-container').on('change', function() {
        $(`.sub-form`).addClass(`d-none`);
        $('input[name="addOrCreate"]').prop(`checked`, false);
    });

    $(`input[name="requestType"]`).on(`change`, function() {
        const requestType = $(this).val();
        if(requestType === "delivery"){
            $(`.quantity-label`).text(`Quantité à livrer`);
        } else if(requestType === "collect") {
            $(`.quantity-label`).text(`Quantité à collecter`);
        } else if(requestType === "purchase") {
            $(`.quantity-label`).text(`Quantité demandée`);
        }
    })

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

    Form.create(`.wii-form`).onSubmit(data => {
        console.error(data.asObject());
    });
});

function cartTypeChange($type) {
    onTypeChange($type);

    const defaultDestinations = JSON.parse($(`#default-delivery-locations`).val());
    const type = $type.val();
    const $destination = $type.closest(`.wii-form`).find(`select[name=destination]`);
    const defaultDestination = defaultDestinations[type] || defaultDestinations['all'];

    $destination.attr(`disabled`, !type);
    $destination.val(null)
    if(defaultDestination) {
        $destination.append(new Option(defaultDestination.label, defaultDestination.id, true, true))
    }
    console.log(defaultDestination, $type.closest(`.wii-form`), $destination);

    $destination.trigger(`change`);
}

function onArticleSelectChange($select) {
    const $selectedOption = $select.find('option:selected');
    const $container = $select.parents('.row');
    const $quantityInput = $container.find('.article-quantity');
    const $quantityToPickInput = $container.find('input[type="number"]');
    const quantity = $selectedOption.data('quantity');

    $quantityInput.val(quantity);
    $quantityToPickInput.attr('max', quantity);
}

function retrieveAppropriateHtml($input) {
    const type = Number.parseInt($input.val());
    const path = Routing.generate('cart_get_appropriate_html', {type});

    $.get(path, function(response) {
        $modalAddToRequest.find('.type-body').html(response.html);
        if (response.count > 0) {
            $('#submitAddToRequest').removeClass('d-none');
        } else {
            showBSAlert(response.message, 'danger');
        }
    });
}

function onPurchaseRequestChange(){
    const $option = $modalAddToRequest.find('option');
    $option.removeClass('d-none');
    const selectedOptionValues = $modalAddToRequest.find('option:selected')
        .map((_, option) => $(option).val())
        .toArray()
        .filter((option) => option);

    const notSelectedOptionSelectors = selectedOptionValues.map((value) => `[value="${value}"]:not(:selected)`).join(',');
    const notSelectedOptions = $modalAddToRequest.find(notSelectedOptionSelectors);
    notSelectedOptions.addClass('d-none');
}

function onDeliveryChanged($select) {
    const val = $select.val();
    $('.display-comment p').remove();

    if($select.val() !== "-"){
        $('.display-comment').append($('input[id='+$select.val()+']').val());
        let pathReferences = Routing.generate("demande_api_references", true);
        let tableDeliveryReferencesConfig = {
            destroy: true,
            serverSide: true,
            processing: true,
            paging: false,
            ajax: {
                "url": pathReferences,
                "type": "POST",
                'data': {
                    'deliveryId': () => $select.val(),
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
    $('.display-comment p').remove();
    $('.collect-objet p').remove();
    $('.collect-destination p').remove();

    if($select.val() !== "-"){
        $('.display-comment').append($('input[id=comment'+$select.val()+']').val());
        if($('input[id=destination'+$select.val()+']').val() === 1){
            $('.collect-destination').append('<p>Mise en stock</p>');
        } else {
            $('.collect-destination').append('<p>Destruction</p>');
        }
        $('.collect-objet').append('<p>'+$('input[id=objet'+$select.val()+']').val()+'</p>');
        let pathReferences = Routing.generate("collecte_api_references", true);
        let tableCollectReferencesConfig = {
            destroy: true,
            serverSide: true,
            processing: true,
            paging: false,
            ajax: {
                "url": pathReferences,
                "type": "POST",
                'data': {
                    'collectId': () => $select.val(),
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
