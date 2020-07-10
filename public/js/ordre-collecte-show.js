let id = $('#collecte-id').val();

let pathArticle = Routing.generate('ordre_collecte_article_api', {'id': id });
let tableArticleConfig = {
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    order: [1, 'asc'],
    columns: [
        { "data": 'Actions', 'title': '', 'orderable': false, className: 'noVis' },
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
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

let modalDeleteOrdreCollecte = $('#modalDeleteOrdreCollecte');
let submitDeleteOrdreCollecte = $('#submitDeleteOrdreCollecte');
let urlDeleteOrdreCollecte = Routing.generate('ordre_collecte_delete',{'id':id}, true);
InitialiserModal(modalDeleteOrdreCollecte, submitDeleteOrdreCollecte, urlDeleteOrdreCollecte, tableArticle, handleRemovalErrors);

function handleRemovalErrors(data) {
    if (!data.success) {
        alertErrorMsg(data.msg, true)
    }
}

let urlFinishCollecte = Routing.generate('ordre_collecte_finish', {'id': id}, true);
let modalFinishCollecte = $("#modalFinishCollecte");
let $submitFinishCollecte = $("#submitFinishCollecte");

$submitFinishCollecte.on('click', function () {
    finishCollecte($(this));
});

function toggleCheck($elem) {
    $elem
        .parents('tr')
        .toggleClass('active')
        .toggleClass('table-success');
}

function checkIfRowSelected(success) {
    let $activeChecks = $('#tableArticle').find('.active');
    if ($activeChecks.length === 0) {
        alertErrorMsg('Veuillez sélectionner au moins une ligne.', true);
    } else {
        success();
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
        const $newTr = $('<tr/>', {id: `finish-collecte-${barCode}`})
            .append($('<td/>', {text: barCode}));

        const $contentLocation = isRef === 0
            ? $('<div class="col-12"><select class="needed form-control ajax-autocompleteEmplacement depositLocation w-100 "></select></div>')
            : $('<span/>', {text: location});

        $newTr.append($('<td/>', {html: $contentLocation}));

        $tbody.append($newTr);
    });
    $('#modalFinishCollecte').modal('show');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));

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
            .find(`#finish-collecte-${barCode}`)
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
        alertErrorMsg('Veuillez sélectionner tous les emplacements de dépose.', true);
    }
    else if (withoutLocation || (rowsData && rowsData.length > 0)) {
        let params = {
            rows: rowsData,
        };
        wrapLoadingOnActionButton($button, () => (
            $.post(urlFinishCollecte, params , (data) => {
                modalFinishCollecte.find('.close').click();
                $('.zone-entete').html(data);
                $rowsToDelete.each(function() {
                    tableArticle
                        .row($(this))
                        .remove()
                        .draw();
                });
                tableArticle.ajax.reload();
            })
        ), false);
    } else {
        modalFinishCollecte.find('.error-msg').html('Veuillez choisir un point de dépose.');
    }
}
function printCollecteBarCodes() {

    const lengthPrintButton = $('.print-button').length;

     if (lengthPrintButton > 0) {
              window.location.href = Routing.generate(
                  'collecte_bar_codes_print',
                  {
                      ordreCollecte: $('#collecte-id').val()
                  },
                 true
             );
         } else {
             alertErrorMsg("Il n'y a aucun article à imprimer.");
     }

}
