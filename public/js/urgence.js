let pathUrgences = Routing.generate('urgence_api', true);
let tableUrgence = $('#tableUrgences').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathUrgences,
        "type": "POST"
    },
    columns:[
        { "data": 'start', 'name' : 'start','title' : 'Date de début' },
        { "data": 'end', 'name' : 'end', 'title' : 'Date de fin' },
        { "data": 'commande', 'name' : 'commande', 'title' : 'Numéro de commande' },
        { "data": 'actions', 'title': 'Actions' },
    ],
    columnDefs: [
        {
            "orderable" : false,
            "targets" : 3
        },
        {
            "type": "customDate",
            "targets": [0, 1]
        }
    ],
});

let $submitSearchUrgence = $('#submitSearchUrgence');

let modalNewUrgence = $('#modalNewUrgence');
let submitNewUrgence = $('#submitNewUrgence');
let urlNewUrgence = Routing.generate('urgence_new');
InitialiserModal(modalNewUrgence, submitNewUrgence, urlNewUrgence, tableUrgence);

let modalDeleteUrgence = $('#modalDeleteUrgence');
let submitDeleteUrgence = $('#submitDeleteUrgence');
let urlDeleteUrgence = Routing.generate('urgence_delete', true);
InitialiserModal(modalDeleteUrgence, submitDeleteUrgence, urlDeleteUrgence, tableUrgence);

let modalModifyUrgence = $('#modalEditUrgence');
let submitModifyUrgence = $('#submitEditUrgence');
let urlModifyUrgence = Routing.generate('urgence_edit', true);
InitialiserModal(modalModifyUrgence, submitModifyUrgence, urlModifyUrgence, tableUrgence);

$submitSearchUrgence.on('click', function () {
    let commande = $('#commandeFilter').val();

    tableUrgence
        .columns('commande:name')
        .search(commande ? '^' + commande + '$' : '', true, false)
        .draw();

    tableUrgence.draw();
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableUrgence.column('start:name').index();

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