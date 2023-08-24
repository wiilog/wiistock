let id = $('#collecte-id').val();

let pathArticle = Routing.generate('ordre_collecte_article_api', {'id': id });
let tableArticleConfig = {
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    order: [['Référence', 'asc']],
    columns: [
        { "data": 'Actions', 'title': '', orderable: false, className: 'noVis' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Libellé', 'title': 'Libellé' },
        { "data": 'Emplacement', 'title': 'Emplacement' },
        { "data": 'Quantité', 'title': 'Quantité' },
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
};
let tableArticle = initDataTable('tableArticle', tableArticleConfig);

let urlEditArticle = Routing.generate('ordre_collecte_edit_article', true);
let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
InitModal(modalEditArticle, submitEditArticle, urlEditArticle, {tables: [tableArticle]});

let urlAddArticle = Routing.generate('ordre_collecte_add_article', true);
let modalAddArticle = $("#modalPickArticle");
let submitAddArticle = $("#submitNewArticle");
InitModal(modalAddArticle, submitAddArticle, urlAddArticle, {tables: [tableArticle]});

let modalDeleteOrdreCollecte = $('#modalDeleteOrdreCollecte');
let submitDeleteOrdreCollecte = $('#submitDeleteOrdreCollecte');
let urlDeleteOrdreCollecte = Routing.generate('ordre_collecte_delete',{'id':id}, true);
InitModal(modalDeleteOrdreCollecte, submitDeleteOrdreCollecte, urlDeleteOrdreCollecte, {tables: [tableArticle]});

let modalNewSensorPairing = $("#modalNewSensorPairing");
let submitNewSensorPairing = $("#submitNewSensorPairing");
let urlNewSensorPairing = Routing.generate('collect_sensor_pairing_new', true)
InitModal(modalNewSensorPairing, submitNewSensorPairing, urlNewSensorPairing, {
    success: () => {
        window.location.reload();
    }
});

let urlFinishCollecte = Routing.generate('ordre_collecte_finish', {'id': id}, true);
let modalFinishCollecte = $("#modalFinishCollecte");
let $submitFinishCollecte = $("#submitFinishCollecte");

$submitFinishCollecte.on('click', function () {
    finishCollecte($(this));
});

function toggleCheck($elem) {
    const $ordreCollecteIntels = $elem.parent('.d-flex').find('.ordre-collecte-data');
    const isManagedByRef = !($ordreCollecteIntels.data('byArticle') === 1);
    const isDestruct = $ordreCollecteIntels.data('is-destruct');
    const quantity = $ordreCollecteIntels.data('quantity');
    const barCode = $ordreCollecteIntels.data('bar-code');
    const id = $ordreCollecteIntels.data('ref-id');
    if (isDestruct || isManagedByRef) {
        $elem
            .parents('tr')
            .toggleClass('active')
            .toggleClass('table-success');
    } else {
        const $modal = $('#modalPickArticle');
        $modal.find('.reference').text(barCode);
        $modal.find('input[name="referenceArticle"]').val(id);
        $modal.find('input[name="quantity-to-pick"]').attr('max', quantity);
        $modal.modal('show');
    }
}

function checkIfRowSelected(success) {
    let $activeChecks = $('#tableArticle').find('.active');
    if ($activeChecks.length === 0) {
        showBSAlert('Veuillez sélectionner au moins une ligne.', 'danger');
    } else {
        success();
    }
}

function printArticles(collecteId) {
    let templates;
    try {
        templates = JSON.parse($('#tagTemplates').val());
    } catch (error) {
        templates = [];
    }
    const params = {
        ordreCollecte: collecteId
    };
    if (templates.length > 0) {
        Promise.all(
            [AJAX.route('GET', `collecte_bar_codes_print`, {forceTagEmpty: true, ...params}).file({})]
                .concat(templates.map(function (template) {
                    params.template = template;
                    return AJAX
                        .route('GET', `collecte_bar_codes_print`, params)
                        .file({})
                }))
        ).then(() => Flash.add('success', 'Impression des étiquettes terminée.'));
    } else {
        AJAX
            .route('GET', `collecte_bar_codes_print`, params)
            .file({
                success: "Votre étiquette a bien été imprimée.",
                error: "Erreur lors de l'impression de l'étiquette"
            });
    }
}

function openLocationModal() {
    let $tbody = $("#modalFinishCollecte div.modal-body table.table > tbody");
    $tbody.empty();
    $('#tableArticle tr.active').each(function () {
        let $tr = $(this);
        let $inputData = $tr.find("input[type='hidden'].ordre-collecte-data");
        let location = $inputData.data('emplacement');
        let isRef = $inputData.data('is-ref');
        let barCode = $inputData.data('barCode');

        const $contentLocation = isRef === 0
            ? $('<div/>', {
                class: 'col-12',
                html: $('<select/>', {
                    class: 'needed form-control ajax-autocomplete-location depositLocation w-100'
                })
            })
            : $('<span/>', {class: 'col-12', text: location});

        const $barCodeTd = $('<td/>', {text: barCode});
        const $locationTd = $('<td/>', {html: $contentLocation});

        const $newTr = $('<tr/>', {'data-barcode': barCode})
            .append($barCodeTd)
            .append($locationTd);

        $tbody.append($newTr);
    });
    $('#modalFinishCollecte').modal('show');
    Select2Old.location($tbody.find('.ajax-autocomplete-location'));
}

function finishCollecte($button, withoutLocation = false) {
    // on récupère les lignes sélectionnées
    let $table = $('#tableArticle');
    let $rowsSelected = $table.find('tr.active');
    let $rowsToDelete = $table.find('tr:not(.active)');
    const rowsData = [];
    let invalidForm = false;

    $rowsSelected.each(function () {
        const $rowData = $(this).find('.ordre-collecte-data');
        const barCode = $rowData.data('bar-code');
        const $select = modalFinishCollecte
            .find(`tr[data-barcode="${barCode}"]`)
            .find('.depositLocation');
        const isRef = $rowData.data('is-ref');
        const depositLocationId = $select.val();
        if (withoutLocation || depositLocationId || isRef === 1 ) {
            rowsData.push({
                'barcode': barCode,
                'is_ref': isRef,
                'quantity': $rowData.data('quantity'),
                depositLocationId
            });
        } else {
            invalidForm = true;
            return false;
        }
    });

    if (invalidForm) {
        showBSAlert('Veuillez sélectionner tous les emplacements de dépose.', 'danger');
    }
    else if (withoutLocation || (rowsData && rowsData.length > 0)) {
        let params = {
            rows: rowsData,
        };
        wrapLoadingOnActionButton($button, () => (
            $.post(urlFinishCollecte, params , (data) => {
                if (data.success) {
                    $('.zone-entete').html(data.entete);
                    $rowsToDelete.each(function () {
                        tableArticle
                            .row($(this))
                            .remove()
                            .draw();
                    });
                    tableArticle.ajax.reload();
                    $('#modalFinishCollecte').modal('hide');
                }
                else {
                    showBSAlert(data.msg, 'danger');
                }
            })
        ));
    } else {
        modalFinishCollecte.find('.error-msg').html('Veuillez choisir un point de dépose.');
    }
}
