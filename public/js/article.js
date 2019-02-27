var path = Routing.generate('article_api', true);
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": path,
        "type": "POST"
    },
    columns:
    [
        { "data": 'Nom' },
        { "data": 'Statut' },
        { "data": 'Reférence article' },
        { "data": 'Emplacement' },
        { "data": 'Destination' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});