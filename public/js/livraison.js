let pathLivraison = Routing.generate('livraison_api');
let tableLivraison = $('#tableLivraison_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[ 2, "desc" ]],
    ajax: {
        'url': pathLivraison,
        "type": "POST"
    },
    columnDefs: [
        {
            "type": "customDate",
            "targets": 2
        }
    ],
    columns: [
    { "data": 'Numéro', 'title': 'Numéro' },
    { "data": 'Statut', 'title': 'Statut'  },
    { "data": 'Date', 'title': 'Date de création' },
    { "data": 'Opérateur', 'title': 'Opérateur'  },
    { "data": 'Actions', 'title': 'Actions'  },
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

let pathArticle = Routing.generate('livraison_article_api', {'id': id });
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
    { "data": 'Référence CEA', 'title': 'Référence CEA' },
    { "data": 'Libellé', 'title': 'Libellé' },
    { "data": 'Emplacement', 'title': 'Emplacement' },
    { "data": 'Quantité', 'title': 'Quantité' },
    { "data": 'Actions', 'title': 'Actions' },
    ],
});

let modalDeleteLivraison = $('#modalDeleteLivraison');
let submitDeleteLivraison = $('#submitDeleteLivraison');
let urlDeleteLivraison = Routing.generate('livraison_delete',{'id':id}, true);
InitialiserModal(modalDeleteLivraison, submitDeleteLivraison, urlDeleteLivraison, tableLivraison);