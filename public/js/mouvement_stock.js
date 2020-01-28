$('.select2').select2();

$(function() {
    initDateTimePicker();
    initSelect2('#emplacement', 'Emplacement');
    initSelect2('#statut', 'Type');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_STOCK);;
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateur');
});

let pathMvt = Routing.generate('mouvement_stock_api', true);
let tableMvt = $('#tableMvts').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#tableMvts_filter input'), tableMvt);
    },
    columns: [
        {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": 'from', 'name': 'from', 'title': 'Issu de'},
        {"data": "refArticle", 'name': 'refArticle', 'title': 'Référence article'},
        {"data": "quantite", 'name': 'quantite', 'title': 'Quantité'},
        {"data": 'origine', 'name': 'origine', 'title': 'Origine'},
        {"data": 'destination', 'name': 'destination', 'title': 'Destination'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: [0, 2]
        }
    ]
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let emplacement = $('#emplacement').val();
        if (emplacement !== '') {
            let originIndex = tableMvt.column('origine:name').index();
            let destinationIndex = tableMvt.column('destination:name').index();
            return data[originIndex] == emplacement || data[destinationIndex] == emplacement;
        } else {
            return true;
        }
    }
);

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableMvt.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

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

let modalDeleteArrivage = $('#modalDeleteMvtStock');
let submitDeleteArrivage = $('#submitDeleteMvtStock');
let urlDeleteArrivage = Routing.generate('mvt_stock_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);
