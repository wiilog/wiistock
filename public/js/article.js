var pathArticle = Routing.generate('article_api', true);
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": pathArticle,
        "type": "POST"
    },
    columns:[
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Référence article' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

