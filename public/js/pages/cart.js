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
            showBSAlert(response.msg, `success`);
            table.ajax.reload();
        });
    })

    $('.request-type-container input[type="radio"]').on('click', function() {
        retrieveAppropriateHtml($(this));
    })
});

function onArticleSelectChange($select) {
    const $selectedOption = $select.find('option:selected');
    const $container = $select.parents('.row');
    const $quantityInput = $container.find('.article-quantity');
    const quantity = $selectedOption.data('quantity');

    $quantityInput.val(quantity);
}

function retrieveAppropriateHtml($input) {
    const type = Number.parseInt($input.val());
    const path = Routing.generate('cart_get_appropriate_html', {type});

    $.get(path, function(html) {
        $modalAddToRequest.find('.type-body').html(html);
        $('#submitAddToRequest').removeClass('d-none');
    });
}
