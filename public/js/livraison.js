$('.select2').select2();

$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Statut');

    // cas d'un filtre par demande de collecte
    let filterDemand = $('#filterDemand').val();

    if (filterDemand) {
        let valueArray = filterDemand.split(':');
        let id = valueArray[0];
        let label = valueArray[1];
        let option = new Option(label, id, true, true);
        $('#demandCollect').append(option).trigger('change');
    }
    else {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ORDRE_LIVRAISON);
        ;
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');

        ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
    }
});

let pathLivraison = Routing.generate('livraison_api');
let tableLivraison = $('#tableLivraison_id').DataTable({
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [
        [3, "desc"]
    ],
    ajax: {
        'url': pathLivraison,
        'data': {
            'filterDemand': $('#filterDemand').val()
        },
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#tableLivraison_id_filter input'), tableLivraison);
    },
    columns: [
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableLivraison.column('Date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin == "" && dateMax == "")
            ||
            (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

let pathArticle = Routing.generate('livraison_article_api', {'id': id});
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': 'Actions'},
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
    ],
    order: [[1, "asc"]],
    columnDefs: [
        {orderable:false, targets: [0]}
    ]
});

let modalDeleteLivraison = $('#modalDeleteLivraison');
let submitDeleteLivraison = $('#submitDeleteLivraison');
let urlDeleteLivraison = Routing.generate('livraison_delete', {'id': id}, true);
InitialiserModal(modalDeleteLivraison, submitDeleteLivraison, urlDeleteLivraison, tableLivraison);
