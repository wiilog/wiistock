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

    $('.request-type-container').on('change', function() {
        $('.existing-collect').addClass('d-none');
        $('.existing-delivery').addClass('d-none');
        $('.create-collect').addClass('d-none');
        $('.create-delivery').addClass('d-none');
        $('input[name="addOrCreate"]').prop('checked', false);
    });

    $(`input[name="addOrCreate"][value="add"]`).on(`click`, function() {
        $typeDemande = $('.request-type-container input[name="requestType"]:checked').val();
        if($typeDemande === "1"){
            $('select[name="delivery-request"]').val("-").trigger('change');
            $('.existing-collect').addClass('d-none');
            $('.existing-delivery').removeClass('d-none');
        }
        else if($typeDemande === "2"){
            //$('select[name="delivery-request"]').select2();
            $('.existing-delivery').addClass('d-none');
            $('.existing-collect').removeClass('d-none');
        }
    })

    $(`input[name="addOrCreate"][value="create"]`).on(`click`, function() {
        if($typeDemande === "1"){
            $('.delivery-request-content').addClass("d-none");
            $('.existing-delivery').addClass('d-none');
        }
        else if($typeDemande === "2"){
            $('.collect-request-content').addClass("d-none");
            $('.existing-collect').addClass('d-none');
        }
    })
});

function validateCart() {
    alert('validé');
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
    $('.comment p').remove();

    if($select.val() !== "-"){
        $('.comment').append($('input[id='+$select.val()+']').val());
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
