$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathService = Routing.generate('service_api', true);
let tableService = $('#tableService_id').DataTable({
    order: [[0, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
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
            let indexDate = tableService.column('Date:name').index();
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

let editorEditServiceAlreadyDone = false;
function initEditServiceEditor(modal) {
    if (!editorEditServiceAlreadyDone) {
        initEditorInModal(modal);
        editorEditServiceAlreadyDone = true;
    }
};

//initialisation editeur de texte une seule fois
let editorNewServiceAlreadyDone = false;
function initNewServiceEditor(modal) {
    if (!editorNewServiceAlreadyDone) {
        initEditor('.editor-container-new');
        editorNewServiceAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    if ($(button).hasClass('not-active')) {
        if ($("#s").val() == "0") {
            $("#s").val("1");
        } else {
            $("#s").val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

$('#submitSearchService').on('keypress', function(e) {
    if (e.which === 13) {
        $('#submitSearchService').click();
    }
});

