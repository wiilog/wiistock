$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Utilisateur',
    }
});

let $submitSearchMvt = $('#submitSearchRecep');
let pathRecep = Routing.generate('recep_traca_api', true);
let tableRecep = $('#tableRecepts').DataTable({
    buttons: [
        {
            extend: 'csv',
            exportOptions: {
                columns: [0, 1, 2, 3]
            }
        }
    ],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "processing": true,
    "order": [[0, "desc"]],
    ajax: {
        "url": pathRecep,
        "type": "POST"
    },
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    columns: [
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "Arrivage", 'name': 'Arrivage', 'title': "Arrivage"},
        {"data": 'Réception', 'name': 'Réception', 'title': 'Réception'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
    ],
});

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateStr = a.split(' ')[0];
        let hourStr = a.split(' ')[1];
        let dateSplitted = dateStr.split('/');
        let hourSplitted = hourStr.split(':');

        let date = new Date(dateSplitted[2], dateSplitted[1], dateSplitted[0], hourSplitted[0], hourSplitted[1], hourSplitted[2]);

        return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), date.getMinutes(), date.getSeconds());
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableRecep.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

let modalDeleteReception = $('#modalDeleteRecepTraca');
let submitDeleteReception = $('#submitDeleteRecepTraca');
let urlDeleteArrivage = Routing.generate('reception_traca_delete', true);
InitialiserModal(modalDeleteReception, submitDeleteReception, urlDeleteArrivage, tableRecep);

$submitSearchMvt.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let arrivage = $('#arrivage').val();
    let reception = $('#reception').val();
    let demandeur = $('#utilisateur').val();
    let demandeurString = demandeur.toString();
    let demandeurPiped = demandeurString.split(',').join('|');

    tableRecep
        .columns('Arrivage:name')
        .search(arrivage ? '^' + arrivage + '$' : '', true, false)
        .draw();

    tableRecep
        .columns('Utilisateur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    tableRecep
        .columns('Réception:name')
        .search(reception ? '^' + reception + '$' : '', true, false)
        .draw();

    tableRecep.draw();
});

let customExport = function() {
    tableRecep.button('.buttons-csv').trigger();
};


