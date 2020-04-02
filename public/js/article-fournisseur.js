let pathArticleFournisseur = Routing.generate('article_fournisseur_api');
let tableArticleFournisseur = $('#tableArticleFournisseur').DataTable({

    processing: true,
    serverSide: true,

    order: [[1, 'desc']],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathArticleFournisseur,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', title: 'Actions'},
        {"data": 'Code Fournisseur', title: 'Code Fournisseur'},
        {"data": 'Référence', title: 'Référence'},
        {"data": 'Article de référence', title: 'Article de référence'},
    ],
    columnDefs: [
        {"orderable": false, "targets": 0}
    ]
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
