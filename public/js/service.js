$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathService = Routing.generate('service_api', true);
let tableService = $('#tableService_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathService,
        "type": "POST"
    },
    columns: [
        { "data": 'Date', 'name': 'Date' },
        { "data": 'Demandeur', 'name': 'Demandeur' },
        { "data": 'Libellé', 'name': 'Libellé' },
        { "data": 'Statut', 'name': 'Statut' },
        { "data": 'Actions', 'name': 'Actions' },
    ],

});

// recherche par défaut demandeur = utilisateur courant
let demandeur = $('.current-username').val();
if (demandeur !== undefined) {
    let demandeurPiped = demandeur.split(',').join('|')
    tableService
        .columns('Demandeur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    // affichage par défaut du filtre select2 demandeur = utilisateur courant
    $('#utilisateur').val(demandeur).trigger('change');
}

// filtres de recheches
$('#submitSearchService').on('click', function () {

    let statut = $('#statut').val();
    let demandeur = [];
    demandeur = $('#utilisateur').val()
    demandeurString = demandeur.toString();
    demandeurPiped = demandeurString.split(',').join('|')

    tableService
        .columns('Statut:name')
        .search(statut)
        .draw();

    tableService
        .columns('Demandeur:name')
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

let modalDeleteService = $('#modalDeleteService');
let submitDeleteService = $('#submitDeleteService');
let urlDeleteService = Routing.generate('service_delete', true);
InitialiserModal(modalDeleteService, submitDeleteService, urlDeleteService, tableService);


let modalShowService = $('#modalDeleteService');
let submitShowService = $('#submitDeleteService');
let urlShowService = Routing.generate('service_show', true);
InitialiserModal(modalShowService, submitShowService, urlShowService, tableService);







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
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    if ($(button).hasClass('not-active')) {
        $("#s").val($(button).data('value'));
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

