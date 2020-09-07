$(function () {
    let tableArticleFournisseur = InitPageDatatable();
    InitPageModal(tableArticleFournisseur);
});


function InitPageDatatable() {
    let pathArticleFournisseur = Routing.generate('article_fournisseur_api');
    let tableArticleFournisseurConfig = {
        processing: true,
        serverSide: true,
        order: [[1, 'desc']],
        ajax: {
            "url": pathArticleFournisseur,
            "type": "POST"
        },
        columns: [
            {"data": 'Actions', title: '', className: 'noVis', orderable: false},
            {"data": 'Référence', title: 'Référence'},
            {"data": 'label', title: 'Libellé'},
            {"data": 'Code Fournisseur', title: 'Code Fournisseur'},
            {"data": 'Article de référence', title: 'Article de référence'},
        ],
        rowConfig: {
            needsRowClickAction: true,
            needsSearchOverride: true
        }
    };
    return initDataTable('tableArticleFournisseur', tableArticleFournisseurConfig);
}

function InitPageModal(tableArticleFournisseur) {
    let $modalNewArticleFournisseur = $("#modalNewArticleFournisseur");
    let $submitNewArticleFournisseur = $("#submitNewArticleFournisseur");
    let urlNewArticleFournisseur = Routing.generate('article_fournisseur_new', true);
    InitModal($modalNewArticleFournisseur, $submitNewArticleFournisseur, urlNewArticleFournisseur, {tables: [tableArticleFournisseur]});

    let $modalDeleteArticleFournisseur = $("#modalDeleteArticleFournisseur");
    let $submitDeleteArticleFournisseur = $("#submitDeleteArticleFournisseur");
    let urlDeleteArticleFournisseur = Routing.generate('article_fournisseur_delete', true);
    InitModal($modalDeleteArticleFournisseur, $submitDeleteArticleFournisseur, urlDeleteArticleFournisseur, {tables: [tableArticleFournisseur]});

    let $modalEditArticleFournisseur = $('#modalEditArticleFournisseur');
    let $submitEditArticleFournisseur = $('#submitEditArticleFournisseur');
    let urlEditArticleFournisseur = Routing.generate('article_fournisseur_edit', true);
    InitModal($modalEditArticleFournisseur, $submitEditArticleFournisseur, urlEditArticleFournisseur, {tables: [tableArticleFournisseur]});
}
