var pathArticle = Routing.generate('article_api', true);
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        "url": "/js/i18n/dataTableLanguage.json"
    },
    ajax:{ 
        "url": pathArticle,
        "type": "POST"
    },
    columns:[
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Libellé' },
        { "data": 'Référence article' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('reception_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

