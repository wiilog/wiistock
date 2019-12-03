let id = $('#collecte-id').val();

let pathArticle = Routing.generate('ordre_collecte_article_api', {'id': id });

let tableArticle = $('#tableArticle').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Libellé', 'title': 'Libellé' },
        { "data": 'Emplacement', 'title': 'Emplacement' },
        { "data": 'Quantité', 'title': 'Quantité' },
        { "data": 'Actions', 'title': 'Actions' },
    ],
});

let urlEditArticle = Routing.generate('ordre_collecte_edit_article', true);
let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

let modalDeleteOrdreCollecte = $('#modalDeleteOrdreCollecte');
let submitDeleteOrdreCollecte = $('#submitDeleteOrdreCollecte');
let urlDeleteOrdreCollecte = Routing.generate('ordre_collecte_delete',{'id':id}, true);
InitialiserModal(modalDeleteOrdreCollecte, submitDeleteOrdreCollecte, urlDeleteOrdreCollecte, tableArticle);

let urlFinishCollecte = Routing.generate('ordre_collecte_finish', {'id': id}, true);
let modalFinishCollecte = $("#modalFinishCollecte");
let submitFinishCollecte = $("#submitFinishCollecte");

submitFinishCollecte.on('click', function() {
    // on récupère les lignes sélectionnées
    let $inactiveChecks = $('#tableArticle').find('.btn-check:not(.active)');
    let rowsData = [];
    $inactiveChecks.each(function() {
        rowsData.push({
            'id': $(this).data('id'),
            'isRef': $(this).data('is-ref')
        });
    });

    // on récupère le point de dépose
    let depositLocationId = modalFinishCollecte.find('.depositLocation').val();

    if (depositLocationId) {
        let params = {'rows': rowsData, 'depositLocationId': depositLocationId};

        $.post(urlFinishCollecte, JSON.stringify(params), (data) => {
            modalFinishCollecte.find('.close').click();
            $('.zone-entete').html(data);
            $inactiveChecks.each(function() {
                tableArticle
                    .row($(this).parents('tr'))
                    .remove()
                    .draw();
            });
        });
    } else {
        modalFinishCollecte.find('.error-msg').html('Veuillez choisir un point de dépose.');
    }
});

function toggleCheck($elem) {
    $elem.toggleClass('active');
}

function checkIfRowSelected() {
    let $activeChecks = $('#tableArticle').find('.active');
    if ($activeChecks.length === 0) {
        alertErrorMsg('Veuillez sélectionner au moins une ligne.', true);
    } else {
        $('#btnModalFinishCollecte').click();
    }

}