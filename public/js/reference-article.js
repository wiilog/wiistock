var path = Routing.generate('ref_article_api', true); 
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: path,
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Actions' },
    ],
});
