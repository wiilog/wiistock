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
let submitFinishCollecte = $("#submitFinishCollecte");

submitFinishCollecte.on('click', function() {
    finishCollecte();
});

function toggleCheck($elem) {
    $elem.toggleClass('active');
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
    $('#btnModalFinishCollecte').trigger('click');
}

function finishCollecte(withoutLocation = false) {
    // on récupère les lignes sélectionnées
    let $table = $('#tableArticle');
    let $rowsSelected = $table.find('.dropdown-item.active');
    let $rowsToDelete = $table.find('.dropdown-item:not(.active)');
    let rowsData = [];
    $rowsSelected.each(function() {
        rowsData.push({
            'reference': $(this).data('ref'),
            'is_ref': $(this).data('is-ref'),
            'quantity': $(this).data('quantity')
        });
    });

    // on récupère le point de dépose
    let depositLocationId = modalFinishCollecte.find('.depositLocation').val();

    if (withoutLocation || depositLocationId) {
        let params = {
            rows: rowsData,
            ...(depositLocationId ? {depositLocationId} : {})
        };

        $.post(urlFinishCollecte, JSON.stringify(params), (data) => {
            modalFinishCollecte.find('.close').click();
            $('.zone-entete').html(data);
            $rowsToDelete.each(function() {
                tableArticle
                    .row($(this).parents('tr'))
                    .remove()
                    .draw();
            });
            tableArticle.ajax.reload();
        });
    } else {
        modalFinishCollecte.find('.error-msg').html('Veuillez choisir un point de dépose.');
    }
}
