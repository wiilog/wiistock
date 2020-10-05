let $modalNewMvtStock = $('#modalNewMvtStock');

$(function() {
    $('.select2').select2();

    let pathMvt = Routing.generate('mouvement_stock_api', true);
    let tableMvtStockConfig = {
        responsive: true,
        serverSide: true,
        processing: true,
        order: [[1, "desc"]],
        ajax: {
            "url": pathMvt,
            "type": "POST"
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {"data": 'actions', 'name': 'Actions', 'title': ''},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'from', 'name': 'from', 'title': 'Issu de', className: 'noVis'},
            {"data": "barCode", 'name': 'barCode', 'title': 'Code barre'},
            {"data": "refArticle", 'name': 'refArticle', 'title': 'Référence article'},
            {"data": "quantite", 'name': 'quantite', 'title': 'Quantité'},
            {"data": 'origine', 'name': 'origine', 'title': 'Origine'},
            {"data": 'destination', 'name': 'destination', 'title': 'Destination'},
            {"data": 'type', 'name': 'type', 'title': 'Type'},
            {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
        ],
        columnDefs: [
            {
                orderable: false,
                targets: [0, 2]
            }
        ]
    };

    let tableMvt = initDataTable('tableMvts', tableMvtStockConfig);

    let modalDeleteArrivage = $('#modalDeleteMvtStock');
    let submitDeleteArrivage = $('#submitDeleteMvtStock');
    let urlDeleteArrivage = Routing.generate('mvt_stock_delete', true);
    InitModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, {tables: [tableMvt]});

    let submitNewMvtStock = $('#submitNewMvtStock');
    let urlNewMvtStock = Routing.generate('mvt_stock_new', true);
    InitModal($modalNewMvtStock, submitNewMvtStock, urlNewMvtStock, {tables: [tableMvt]});

    initDateTimePicker();
    Select2.init($('#emplacement'), 'Emplacements');
    Select2.init($('#statut'), 'Types');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_STOCK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    Select2.user('Opérateurs');
    Select2.location($('.ajax-autocomplete-emplacements'), {}, "Emplacements", 3);
    Select2.articleReference($modalNewMvtStock.find('.select2-autocomplete-ref-articles'));
});

function newMvtStockArticleChosen($select) {
    newMvtStockReferenceChosen($select);
    const $artMvt = $('#chosen-art-barcode');
    const $refMvt = $('#chosen-ref-barcode');
    const $location = $('#chosen-ref-location');
    const $quantity = $('#chosen-ref-quantity');
    const $type = $('#type-new-mvt');
    const $locationTo = $('#chosen-mvt-location');
    const $quantityEntranceOut = $('#chosen-mvt-quantity');

    const selectedArticles = $select.select2('data');

    if (selectedArticles.length > 0) {
        const selectedArticle = selectedArticles[0];
        const typeQuantity = selectedArticle.typeQuantity;
        const $fieldToHide = typeQuantity === 'article' ? $refMvt : $artMvt;
        const $fieldToShow = typeQuantity === 'article' ? $artMvt : $refMvt;

        $fieldToShow.addClass('needed').addClass('data');
        $fieldToShow.parent().removeClass('d-none');

        $fieldToHide.parent().addClass('d-none');

        $location.parent().removeClass('needed').addClass('d-none');
        $quantity.parent().removeClass('needed').addClass('d-none');
        $type.parent().removeClass('needed').addClass('d-none');
        $locationTo.parent().removeClass('needed').addClass('d-none');
        $quantityEntranceOut.parent().removeClass('needed').addClass('d-none');

        const $selectArticles = $modalNewMvtStock.find('.select2-autocomplete-articles');
        if ($selectArticles.hasClass('select2-hidden-accessible')) {
            $selectArticles.select2('destroy');
            $selectArticles.val(null);
            $type.val(null);
        }

        if(typeQuantity === 'reference') {
            $location.parent().addClass('needed').removeClass('d-none');
            $quantity.parent().addClass('needed').removeClass('d-none');
            $type.parent().addClass('needed').removeClass('d-none');
            $artMvt.removeClass('needed');
        }
        Select2.article($selectArticles, selectedArticle.text, 0);
    }
}

function showFieldsAndFillOnArticleChange($select) {
    let currentArticle = $select.select2('data')[0];
    const $location = $('#chosen-ref-location');
    const $quantity = $('#chosen-ref-quantity');
    const $type = $('#type-new-mvt');
    const $barcodeInput = $('input[name="movement-barcode"]');
    if (currentArticle) {

        $location.parent().addClass('needed').removeClass('d-none');
        $quantity.parent().addClass('needed').removeClass('d-none');
        $type.parent().addClass('needed').removeClass('d-none');

        const $articleLocation = $modalNewMvtStock.find('#chosen-ref-location');
        const $articleQuantity = $modalNewMvtStock.find('#chosen-ref-quantity');
        $barcodeInput.val(currentArticle.text);
        $articleLocation.val(currentArticle.locationLabel);
        $articleQuantity.val(currentArticle.quantity);
    }
}

function newMvtStockReferenceChosen($select) {
    let referenceArticle = $select.select2('data')[0];
    if (referenceArticle) {
        const $referenceLibelle = $modalNewMvtStock.find('#chosen-ref-label');
        const $referenceBarCode = $modalNewMvtStock.find('#chosen-ref-barcode');
        const $referenceLocation = $modalNewMvtStock.find('#chosen-ref-location');
        const $referenceQuantite = $modalNewMvtStock.find('#chosen-ref-quantity');
        const $typeMvt = $modalNewMvtStock.find('#type-new-mvt');

        $referenceLibelle.val(referenceArticle.libelle);
        $referenceBarCode.val(referenceArticle.barCode);
        $referenceQuantite.val(referenceArticle.quantiteDisponible);
        $referenceLocation.val(referenceArticle.location);

        $modalNewMvtStock.find('.is-hidden-by-ref').removeClass('d-none');
        $typeMvt.addClass('needed');
    }
}

function resetNewModal($modal) {
    const $typeMvt = $modal.find('#type-new-mvt');
    $modal.find('.is-hidden-by-ref').addClass('d-none');
    $modal.find('.is-hidden-by-type').addClass('d-none');
    $('#chosen-art-barcode').parent().addClass('d-none');
    $typeMvt.removeClass('needed');
    $modal.find('.select2-autocomplete-ref-articles').empty();
    $modal.find('.select2-autocomplete-articles').empty();
}

function newMvtStockTypeChanged($select) {
    const $locationMvt = $('.select2-emplacement');
    const $quantityMvt = $('#chosen-mvt-quantity');

    const $selectOption = $select.find('option:selected');

    if ($selectOption.data('needs-location')) {
        Select2.location($locationMvt);
        $locationMvt.addClass('needed');
        $locationMvt.parents('.form-group').removeClass('d-none');

        $quantityMvt.removeClass('needed');
        $quantityMvt.parents('.form-group').addClass('d-none');
    } else if ($selectOption.data('needs-quantity')) {

        if ($selectOption.data('needs-quantity-cap')) {
            $quantityMvt.attr('max', $('#chosen-ref-quantity').val());
        } else {
            $quantityMvt.attr('max', '');
        }

        $quantityMvt.addClass('needed');
        $quantityMvt.parents('.form-group').removeClass('d-none');

        $locationMvt.removeClass('needed');
        $locationMvt.parents('.form-group').addClass('d-none');
    }
}
