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
    InitialiserModal(
        $modalNewArticleFournisseur,
        $submitNewArticleFournisseur,
        urlNewArticleFournisseur,
        tableArticleFournisseur,
        handleFormSuccess($modalNewArticleFournisseur),
        false,
        false
    );

    let $modalDeleteArticleFournisseur = $("#modalDeleteArticleFournisseur");
    let $submitDeleteArticleFournisseur = $("#submitDeleteArticleFournisseur");
    let urlDeleteArticleFournisseur = Routing.generate('article_fournisseur_delete', true);
    InitialiserModal(
        $modalDeleteArticleFournisseur,
        $submitDeleteArticleFournisseur,
        urlDeleteArticleFournisseur,
        tableArticleFournisseur
    );

    let $modalEditArticleFournisseur = $('#modalEditArticleFournisseur');
    let $submitEditArticleFournisseur = $('#submitEditArticleFournisseur');
    let urlEditArticleFournisseur = Routing.generate('article_fournisseur_edit', true);
    InitialiserModal(
        $modalEditArticleFournisseur,
        $submitEditArticleFournisseur,
        urlEditArticleFournisseur,
        tableArticleFournisseur,
        handleFormSuccess($modalEditArticleFournisseur),
        false,
        false
    );
}

function handleFormSuccess($modal) {
    return (data) => {
        if (data.success){
            $modal.modal('hide');
            clearModal($modal);
        }
        else {
            $modal.find('.error-msg').text(data.message);
        }
    }
}
