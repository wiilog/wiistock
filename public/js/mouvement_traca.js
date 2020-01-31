$('.select2').select2();

$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Type');
    initSelect2('#emplacement', 'Emplacement');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_TRACA);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
});

let pathMvt = Routing.generate('mvt_traca_api', true);
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
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "colis", 'name': 'colis', 'title': "Colis"},
        {"data": 'location', 'name': 'location', 'title': 'Emplacement'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
});

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

let modalNewMvtTraca = $("#modalNewMvtTraca");
let submitNewMvtTraca = $("#submitNewMvtTraca");
let urlNewMvtTraca = Routing.generate('mvt_traca_new', true);
initModalWithAttachments(modalNewMvtTraca, submitNewMvtTraca, urlNewMvtTraca, tableMvt);

let modalEditMvtTraca = $("#modalEditMvtTraca");
let submitEditMvtTraca = $("#submitEditMvtTraca");
let urlEditMvtTraca = Routing.generate('mvt_traca_edit', true);
initModalWithAttachments(modalEditMvtTraca, submitEditMvtTraca, urlEditMvtTraca, tableMvt);

let modalDeleteArrivage = $('#modalDeleteMvtTraca');
let submitDeleteArrivage = $('#submitDeleteMvtTraca');
let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

let editorNewMvtTracaAlreadyDone = false;

function initNewMvtTracaEditor(modal) {
    if (!editorNewMvtTracaAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewMvtTracaAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'));
};

let editorEditMvtTracaAlreadyDone = false;

function initEditMvtTracaEditor(modal) {
    if (!editorEditMvtTracaAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-edit');
        editorEditMvtTracaAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
    ajaxAutoUserInit($('.ajax-autocomplete-user-edit'));
};

function fillDateInNewModal() {
    const date = moment().format();
    $('#modalNewMvtTraca').find('.datetime').val(date.slice(0,16));
}
