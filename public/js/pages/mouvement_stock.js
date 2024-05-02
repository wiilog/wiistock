let $modalNewMvtStock = $('#modalNewMvtStock');
let tableMvt = null;

$(function() {
    $('.select2').select2();

    AJAX.route(
        AJAX.GET,
        "mouvement_stock_api_columns",
        {}
    )
        .json()
        .then((columns) => {
            let pathMvt = Routing.generate('mouvement_stock_api', true);
            let tableMvtStockConfig = {
                responsive: true,
                serverSide: true,
                processing: true,
                order: [['date', "desc"]],
                ajax: {
                    "url": pathMvt,
                    "type": "POST"
                },
                drawConfig: {
                    needsSearchOverride: true,
                    needsColumnHide: true,
                },
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableMvts'
                },
                columns: columns,
            };
            tableMvt = initDataTable('tableMvts', tableMvtStockConfig);
        })

    Form
        .create('#modalNewMvtStock',{clearOnOpen: true})
        .submitTo(AJAX.POST, "mvt_stock_new", {tables: [tableMvt]})
        .on('change', '[name="reference-new-mvt"]', (event) => {
            newMvtStockArticleChosen($(event.target));
        })
        .on('change', '[name="chosen-type-mvt"]', (event) => {
            newMvtStockTypeChanged($(event.target));
        })
        .on('change', '[name="chosen-art-barcode"]', (event) => {
            showFieldsAndFillOnArticleChange($(event.target));
        })

    initDateTimePicker();
    Select2Old.init($('#emplacement'), 'Emplacements');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_STOCK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    Select2Old.user('Opérateurs');
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacements", 3);
    Select2Old.articleReference($modalNewMvtStock.find('.select2-autocomplete-ref-articles'));
});

function newMvtStockArticleChosen($select) {
    newMvtStockReferenceChosen($select);
    const $artMvt = $('[name="chosen-art-barcode"]');
    const $refMvt = $('[name="chosen-ref-barcode"]');
    const $location = $('[name="chosen-ref-location"]');
    const $quantity = $('[name="chosen-ref-quantity"]');
    const $type = $('[name="chosen-type-mvt"]');
    const $locationTo = $('[name="chosen-mvt-location"]');
    const $quantityEntranceOut = $('[name="chosen-mvt-quantity"]');

    const selectedArticles = $select.select2('data');

    if (selectedArticles.length > 0) {
        const selectedArticle = selectedArticles[0];
        const typeQuantity = selectedArticle.typeQuantite;
        const $fieldToHide = typeQuantity === 'article' ? $refMvt : $artMvt;
        const $fieldToShow = typeQuantity === 'article' ? $artMvt : $refMvt;

        $fieldToShow.addClass('needed').addClass('data');
        $fieldToShow.parent().parent().removeClass('d-none');

        $fieldToHide.parent().parent().addClass('d-none');

        $location.parent().parent().removeClass('needed').addClass('d-none');
        $quantity.parent().parent().removeClass('needed').addClass('d-none');
        $type.parent().parent().removeClass('needed').addClass('d-none');
        $locationTo.parent().parent().removeClass('needed').addClass('d-none');
        $quantityEntranceOut.parent().parent().removeClass('needed').addClass('d-none');

        const $selectArticles = $modalNewMvtStock.find('.select2-autocomplete-articles');
        if ($selectArticles.hasClass('select2-hidden-accessible')) {
            $selectArticles.select2('destroy');
            $selectArticles.val(null);
            $type.val(null);
        }

        if(typeQuantity === 'reference') {
            $location.parent().parent().addClass('needed').removeClass('d-none');
            $quantity.parent().parent().addClass('needed').removeClass('d-none');
            $type.parent().parent().addClass('needed').removeClass('d-none');
            $artMvt.removeClass('needed');
        }
        Select2Old.article($selectArticles, selectedArticle.text, 0);
    }
}

function showFieldsAndFillOnArticleChange($select) {
    let currentArticle = $select.select2('data')[0];
    const $location = $('[name="chosen-ref-location"]');
    const $quantity = $('[name="chosen-ref-quantity"]');
    const $type = $('[name="chosen-type-mvt"]');
    const $barcodeInput = $('input[name="chosen-ref-barcode"]');
    if (currentArticle) {

        $location.parent().parent().addClass('needed').removeClass('d-none');
        $quantity.parent().parent().addClass('needed').removeClass('d-none');
        $type.parent().parent().addClass('needed').removeClass('d-none');

        const $articleLocation = $modalNewMvtStock.find('[name="chosen-ref-location"]');
        const $articleQuantity = $modalNewMvtStock.find('[name="chosen-ref-quantity"]');
        $barcodeInput.val(currentArticle.text);
        $articleLocation.val(currentArticle.location);
        $articleQuantity.val(currentArticle.quantity);
    }
}

function newMvtStockReferenceChosen($select) {
    let referenceArticle = $select.select2('data')[0];
    if (referenceArticle) {
        const $referenceLibelle = $modalNewMvtStock.find('[name="chosen-ref-label"]');
        const $referenceBarCode = $modalNewMvtStock.find('[name="chosen-ref-barcode"]');
        const $referenceLocation = $modalNewMvtStock.find('[name="chosen-ref-location"]');
        const $referenceQuantite = $modalNewMvtStock.find('[name="chosen-ref-quantity"]');
        const $typeMvt = $modalNewMvtStock.find('[name="chosen-type-mvt"]');

        $referenceLibelle.val(referenceArticle.label);
        $referenceBarCode.val(referenceArticle.barCode);
        $referenceQuantite.val(referenceArticle.quantityDisponible);
        $referenceLocation.val(referenceArticle.location);

        $modalNewMvtStock.find('.is-hidden-by-ref').removeClass('d-none');
        $typeMvt.addClass('needed');
    }
}

function resetNewModal($modal) {
    const $typeMvt = $modal.find('[name="chosen-type-mvt"]');
    $modal.find('.is-hidden-by-ref').addClass('d-none');
    $modal.find('.is-hidden-by-type').addClass('d-none');
    $('[name="chosen-art-barcode"]').parent().parent().addClass('d-none');
    $typeMvt.removeClass('needed');
    $modal.find('.select2-autocomplete-ref-articles').empty();
    $modal.find('.select2-autocomplete-articles').empty();
}

function newMvtStockTypeChanged($select) {
    const $locationMvt = $('[name="chosen-mvt-location"]');
    const $quantityMvt = $('[name="chosen-mvt-quantity"]');

    const $selectOption = $select.find('option:selected');
    if ($selectOption.data('needs-location')) {
        Select2Old.location($locationMvt);
        $locationMvt.addClass('needed');
        $locationMvt.parents('.form-group').removeClass('d-none');
        $locationMvt.parents('.labelLocationMovement').removeClass('d-none');

        $quantityMvt.removeClass('needed');
        $quantityMvt.parents('.form-group').addClass('d-none');
    } else if ($selectOption.data('needs-quantity')) {
        $quantityMvt.addClass('needed');
        $quantityMvt.parents('.form-group').removeClass('d-none');
        $quantityMvt.parents('.labelQuantityMovement').removeClass('d-none');

        $locationMvt.removeClass('needed');
        $locationMvt.parents('.form-group').addClass('d-none');

        if ($selectOption.data('needs-quantity-cap')) {
            Select2Old.location($locationMvt);
            $quantityMvt.attr('max', $('[name="chosen-ref-quantity"]').val());
            $locationMvt.addClass('needed');
            $locationMvt.parents('.form-group').removeClass('d-none');
            $locationMvt.parents('.labelLocationMovement').removeClass('d-none');
        } else {
            $quantityMvt.removeAttr('max');
        }
    }
}

function deleteMvtStock(id) {
    Modal.confirm({
        ajax: {
            method: "DELETE",
            route: `mvt_stock_delete`,
            params: {mvtStock: id},
        },
        message: `Voulez-vous réellement supprimer ce mouvement ?`,
        title: `Supprimer le mouvement`,
        validateButton: {
            color: `danger`,
            label: `Supprimer`,
        },
        table: tableMvt,
    })
}
