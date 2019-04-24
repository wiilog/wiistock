let id = $('#collecte-id').val();

let pathArticle = Routing.generate('ordre_collecte_article_api', {'id': id });

let tableArticle = $('#tableArticle').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence CEA', 'title': 'Référence CEA' },
        { "data": 'Libellé', 'title': 'Libellé' },
        { "data": 'Quantité', 'title': 'Quantité' },
        { "data": 'Actions', 'title': 'Actions' },
    ],
});

let urlEditArticle = Routing.generate('ordre_collecte_edit_article', true);
let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);