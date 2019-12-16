let pathAlerte = Routing.generate("alerte_ref_api", true);
let tableAlerte = $('#tableAlerte_id').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathAlerte,
        "type": "POST",
    },
    columns: [
        { "data": 'Label', 'title': 'Libellé' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'QuantiteStock', 'title': 'Quantité en stock' },
        { "data": "SeuilAlerte", 'title': "Seuil d'alerte" },
        { "data": 'SeuilSecurite', 'title': 'Seuil de sécurité' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Alerte'},
    ],
    columnDefs: [
        { "orderable": false, "targets": 5 }
    ],
});
