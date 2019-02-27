var path = Routing.generate('preparation_api');
var table = $('#table_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: path,
    columns: [
        { "data": 'Numéro' },
        { "data": 'Date' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});
