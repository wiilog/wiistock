let pathLivraison = Routing.generate('livraison_api');
let tableLivraison = $('#tableLivraison_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "order": [[ 2, "desc" ]],
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
    { "data": 'Référence CEA', 'title': 'Référence CEA' },
    { "data": 'Libellé', 'title': 'Libellé' },
    { "data": 'Quantité', 'title': 'Quantité' },
    ],
});

let modalDeleteLivraison = $('#modalDeleteLivraison');
let submitDeleteLivraison = $('#submitDeleteLivraison');
let urlDeleteLivraison = Routing.generate('livraison_delete',{'id':id}, true);
InitialiserModal(modalDeleteLivraison, submitDeleteLivraison, urlDeleteLivraison, tableLivraison);