$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

let $submitSearchOrdreCollecte = $('#submitSearchOrdreCollecte');

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
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
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
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ORDRE_COLLECTE);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0) $submitSearchOrdreCollecte.click();
    }, 'json');

    ajaxAutoDemandCollectInit($('.ajax-autocomplete-dem-collecte'));
});

$submitSearchOrdreCollecte.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let type = $('#type').val();
    let utilisateur = $('#utilisateur').val();
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    let demandCollect = $('#demandCollect').val();
    saveFilters(PAGE_ORDRE_COLLECTE, dateMin, dateMax, statut, utilisateurPiped, type, null, null, null, null, demandCollect);

    tableCollecte
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableCollecte
        .columns('Type:name')
        .search(type ? '^' + type + '$' : '', true, false)
        .draw();

    tableCollecte
        .columns('Opérateur:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    tableCollecte.draw();
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