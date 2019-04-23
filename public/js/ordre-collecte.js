let pathCollecte = Routing.generate('ordre_collecte_api');
let tableCollecte = $('#tableCollecte').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "order": [[ 2, "desc" ]],
    ajax: {
        'url': pathCollecte,
        "type": "POST"
    },
    columns: [
    { "data": 'Numéro', 'title': 'Numéro' },
    { "data": 'Statut', 'title': 'Statut' },
    { "data": 'Date', 'title': 'Date' },
    { "data": 'Opérateur', 'title': 'Opérateur' },
    { "data": 'Actions', 'title': 'Actions' },
    ],
});