$('.select2').select2();

let pathCollecte = Routing.generate('ordre_collecte_api');

let tableCollecte = $('#tableCollecte').DataTable({
    serverSide: true,
    processing: true,
    order: [[3, 'desc']],
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathCollecte,
        'data' : {
          'filterDemand': $('#filterDemandId').val()
        },
        "type": "POST"
    },
    drawCallback: function() {
        overrideSearch($('#tableCollecte_filter input'), tableCollecte);
    },
    columns: [
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableCollecte.column('Date:name').index();

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

$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Statut');
    ajaxAutoDemandCollectInit($('.ajax-autocomplete-dem-collecte'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');

    // cas d'un filtre par demande de collecte
    let $filterDemand = $('.filters-container .filter-demand');
    $filterDemand.attr('name', 'demCollecte');
    $filterDemand.attr('id', 'demCollecte');
    let filterDemandId = $('#filterDemandId').val();
    let filterDemandValue = $('#filterDemandValue').val();

    if (filterDemandId && filterDemandValue) {
        let option = new Option(filterDemandValue, filterDemandId, true, true);
        $filterDemand.append(option).trigger('change');
    } else {

        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ORDRE_COLLECTE);

        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});
