let pathLivraison = Routing.generate('livraison_api');
let tableLivraison = $('#tableLivraison_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        'url': pathLivraison,
        "type": "POST"
    },
    columns: [
    { "data": 'Numéro' },
    { "data": 'Statut' },
    { "data": 'Date' },
    { "data": 'Opérateur' },
    { "data": 'Actions' },
    ],
});


let pathArticle = Routing.generate('livraison_article_api', {'id': id });
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
    { "data": 'Référence CEA' },
    { "data": 'Libellé' },
    { "data": 'Quantité' },
    { "data": 'Actions' },
    ],
});