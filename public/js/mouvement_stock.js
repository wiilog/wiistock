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
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

let modalNewMvtStock = $('#modalNewMvtStock');
let submitNewMvtStock = $('#submitNewMvtStock');
let urlNewMvtStock = Routing.generate('mvt_stock_new', true);
InitialiserModal(modalNewMvtStock, submitNewMvtStock, urlNewMvtStock, tableMvt);

$(function() {
    initDateTimePicker();
    initSelect2($('#emplacement'), 'Emplacements');
    initSelect2($('#statut'), 'Types');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_STOCK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacements", 3);
    ajaxAutoRefArticleInit(modalNewMvtStock.find('.select2-autocomplete-ref-articles'));
});


function newMvtStockReferenceChosen($select) {
    let referenceArticle = $select.select2('data')[0];
    console.log($select.select2('data'));
    if (referenceArticle) {
        const $referenceLibelle = modalNewMvtStock.find('#chosen-ref-label');
        const $referenceBarCode = modalNewMvtStock.find('#chosen-ref-barcode');
        const $referenceLocation = modalNewMvtStock.find('#chosen-ref-location');
        const $referenceQuantite = modalNewMvtStock.find('#chosen-ref-quantity');
        const $typeMvt = modalNewMvtStock.find('#type-new-mvt');

        $referenceLibelle.val(referenceArticle.libelle);
        $referenceBarCode.val(referenceArticle.barCode);
        $referenceQuantite.val(referenceArticle.quantiteDisponible);
        $referenceLocation.val(referenceArticle.location);

        modalNewMvtStock.find('.is-hidden-by-ref').removeClass('d-none');
        $typeMvt.addClass('needed');
    }
}

function resetNewModal($modal) {
    const $typeMvt = $modal.find('#type-new-mvt');
    $modal.find('.is-hidden-by-ref').addClass('d-none');
    $modal.find('.is-hidden-by-type').addClass('d-none');
    $typeMvt.removeClass('needed');
    $modal.find('.select2-autocomplete-ref-articles').empty();
}

function newMvtStockTypeChanged($select) {
    const $locationMvt = $('.select2-emplacement');
    const $quantityMvt = $('#chosen-mvt-quantity');

    const $selectOption = $select.find('option:selected');

    if ($selectOption.data('needs-location')) {
        ajaxAutoCompleteEmplacementInit($locationMvt);
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

