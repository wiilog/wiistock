$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
         text: 'Demandeur',
    }
});

var pathService = Routing.generate('service_api', true);
var tableService = $('#tableService_id').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathService,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Libellé' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],

});
// filtres de recheches
$('#submitSearchService').on('click', function () {

    let statut = $('#statut').val();
    let demandeur = [];
    demandeur = $('#utilisateur').val()
    demandeurString = demandeur.toString();
    demandeurPiped = demandeurString.split(',').join('|')

    tableService
        .columns(3)
        .search(statut)
        .draw();

    tableService
        .columns(1)
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let dateInit = (data[0]).split('/').reverse().join('-') || 0;

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
    tableService
        .draw();
});

let modalNewService = $("#modalNewService");
let submitNewService = $("#submitNewService");
let urlNewService = Routing.generate('service_new', true);
InitialiserModal(modalNewService, submitNewService, urlNewService, tableService);

let modalModifyService = $('#modalEditService');
let submitModifyService = $('#submitEditService');
let urlModifyService = Routing.generate('service_edit', true);
InitialiserModal(modalModifyService, submitModifyService, urlModifyService, tableService);

var editorEditServiceAlreadyDone = false;
function initEditServiceEditor(modal) {
    if (!editorEditServiceAlreadyDone) {
        initEditor(modal);
        editorEditServiceAlreadyDone = true;

    }
};

//initialisation editeur de texte une seule fois
var editorNewServiceAlreadyDone = false;
function initNewServiceEditor(modal) {
    if (!editorNewServiceAlreadyDone) {
        initEditor(modal);
        editorNewServiceAlreadyDone = true;
    }
};
