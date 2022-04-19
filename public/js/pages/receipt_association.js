$(`.select2`).select2();

$(function () {
    const tableReceiptAssociation = initDatatable();

    initDateTimePicker();
    initModals(tableReceiptAssociation);
    Select2Old.user('Utilisateurs');

    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_RECEIPT_ASSOCIATION);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, `json`);

    const $modalNewReceiptAssociation = $(`#modalNewReceiptAssociation`);
    $modalNewReceiptAssociation.on('hide.bs.modal', function () {
        $('.pack-code-container').not(':first').remove();
        $('.reception-number-container').not(':first').remove();

        const $toggleArrival = $('.toggle-arrival');

        if(!$toggleArrival.data(`arrival`)) {
            toggleArrivage($toggleArrival);
        }
    });

    $modalNewReceiptAssociation.on('shown.bs.modal', () => {
        $('input[name=packCode]').trigger('focus');
    });

    $('.packs-wrapper').on('keypress', 'input[name=packCode]', function(e) {
        if(e.originalEvent.key === 'Enter') {
            const $nextParent = $(this).parent().next();
            if($nextParent.is('.pack-code-container')) {
                $nextParent.find('input[name=packCode]').trigger('focus');
            } else {
                $('input[name=receptionNumber]').first().trigger('focus');
            }
        }
    });

    $('.receptions-wrapper').on('keypress', 'input[name=receptionNumber]', function(e) {
        if(e.originalEvent.key === 'Enter') {
            const $nextParent = $(this).parent().next();
            if($nextParent.is('.reception-number-container')) {
                $nextParent.find('input[name=receptionNumber]').trigger('focus');
            } else {
                $('#submitNewReceiptAssociation').trigger('click');
            }
        }
    });
});

function initDatatable() {
    let pathReceiptAssociation = Routing.generate(`receipt_association_api`, true);
    let tableReceiptAssociationConfig = {
        serverSide: true,
        processing: true,
        order: [[1, `desc`]],
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true
        },
        ajax: {
            url: pathReceiptAssociation,
            type: `POST`
        },
        columns: [
            {data: `Actions`, name: `Actions`, title: ``, className: `noVis`, orderable: false},
            {data: `creationDate`, name: `creationDate`, title: `Date`},
            {data: `packCode`, name: `pack`, title: `Colis`},
            {data: `receptionNumber`, name: `receptionNumber`, title: `réception.Réception`, translated: true},
            {data: `user`, name: `user`, title: `Utilisateur`},
        ],
    };
    return initDataTable(`receiptAssociationTable`, tableReceiptAssociationConfig)
}

function initModals(tableReceiptAssociation) {
    let modalNewReceiptAssociation = $(`#modalNewReceiptAssociation`);
    let submitNewReceiptAssociation = $(`#submitNewReceiptAssociation`);
    let urlNewReceiptAssociation = Routing.generate(`receipt_association_new`, true);
    InitModal(modalNewReceiptAssociation, submitNewReceiptAssociation, urlNewReceiptAssociation, {
        tables: [tableReceiptAssociation],
        keepModal: true,
        clearOnClose: true,
        keepForm: true,
        success: () => {
            $('#beep')[0].play();
            clearModal(modalNewReceiptAssociation);
            $('.packs-wrapper').find('.pack-code-container').not(':first').remove();
            $('.receptions-wrapper').find('.reception-number-container').not(':first').remove();
            $('input[name=packCode]').trigger('focus');
        }
    });

    let modalDeleteReceiptAssociation = $(`#modalDeleteReceiptAssociation`);
    let submitDeleteReceiptAssociation = $(`#submitDeleteReceiptAssociation`);
    let urlDeleteReceiptAssociation = Routing.generate(`receipt_association_delete`, true);
    InitModal(modalDeleteReceiptAssociation, submitDeleteReceiptAssociation, urlDeleteReceiptAssociation, {tables: [tableReceiptAssociation]});
}

function newLine($span) {
    let $input = '';
    if($span.siblings('div').first().hasClass('pack-code-container')) {
        $input = $span.parent().find('.pack-code-container').first();
    } else {
        $input = $span.parent().find('.reception-number-container').first();
    }

    const $parent = $input.parent();
    $input.clone().appendTo($parent).find('input[type=text]').val("");
    $input.parent().find('input[type=text]').trigger('focus');
}

function toggleArrivage(button) {
    const $packCodeContainers = $('.pack-code-container');
    const $packCodeInputs = $packCodeContainers.find('input[name=packCode]');
    if (button.data('arrival')) {
        $packCodeContainers.not(':first').remove();

        const $firstPackCodeInput = $packCodeContainers.find('input').first();
        $firstPackCodeInput.val('')
        $firstPackCodeInput.removeClass('needed');

        $packCodeContainers.parent().addClass('d-none');
        button.text('Avec arrivage');

        $packCodeInputs.removeClass('data-array');
    } else {
        $packCodeContainers.find('input').each(function () {
            $(this).addClass('needed');
        });
        $packCodeContainers.parent().removeClass('d-none');
        button.text('Sans arrivage');
        $packCodeInputs.addClass('data-array');
    }
    button.data('arrival', !button.data('arrival'));
}
