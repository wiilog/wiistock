let pathArticleFournisseur = Routing.generate('article_fournisseur_api');
let tableArticleFournisseur = $('#tableArticleFournisseur').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{
        "url": pathArticleFournisseur,
        "type": "POST"
    }, 
        columns: [
        { "data": 'Fournisseur' },
        { "data": 'Référence' },
        { "data": 'Article de référence' },
        { "data": 'Actions' },
    ],
});

let modalNewArticleFournisseur = $("#modalNewArticleFournisseur");
let submitNewArticleFournisseur = $("#submitNewArticleFournisseur");
let urlNewArticleFournisseur = Routing.generate('article_fournisseur_new', true);
InitialiserModal(modalNewArticleFournisseur, submitNewArticleFournisseur, urlNewArticleFournisseur, tableArticleFournisseur);

let modalDeleteArticleFournisseur = $("#modalDeleteArticleFournisseur");
let submitDeleteArticleFournisseur = $("#submitDeleteArticleFournisseur");
let urlDeleteArticleFournisseur = Routing.generate('article_fournisseur_delete', true)
InitialiserModal(modalDeleteArticleFournisseur, submitDeleteArticleFournisseur, urlDeleteArticleFournisseur, tableArticleFournisseur);

let modalEditArticleFournisseur = $('#modalEditArticleFournisseur');
let submitEditArticleFournisseur = $('#submitEditArticleFournisseur');
let urlEditArticleFournisseur = Routing.generate('article_fournisseur_edit', true);
InitialiserModal(modalEditArticleFournisseur, submitEditArticleFournisseur, urlEditArticleFournisseur, tableArticleFournisseur);