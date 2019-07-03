let pathCollecte = Routing.generate('ordre_collecte_api');

let tableCollecte = $('#tableCollecte').DataTable({
    order: [[2, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 2
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathCollecte,
        "type": "POST"
    },
    columns: [
    { "data": 'Numéro', 'title': 'Numéro' },
    { "data": 'Statut', 'title': 'Statut' },
    { "data": 'Date', 'title': 'Date de création' },
    { "data": 'Opérateur', 'title': 'Opérateur' },
    { "data": 'Actions', 'title': 'Actions' },
    ],
});

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateParts = a.split('/'),
            year = parseInt(dateParts[2]) - 1900,
            month = parseInt(dateParts[1]),
            day = parseInt(dateParts[0]);
        return Date.UTC(year, month, day, 0, 0, 0);
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});