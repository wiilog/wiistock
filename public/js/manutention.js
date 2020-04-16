$('.select2').select2();

let $submitSearchManut = $('#submitSearchManutention');

let pathManut = Routing.generate('manutention_api', true);
let tableManutention = $('#tableManutention_id').DataTable({
    serverSide: true,
    processing: true,
    order: [[1, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 1
        },
        {
            "orderable" : false,
            "targets" : 0
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    rowCallback: function(row, data) {
        initActionOnRow(row);
    },
    ajax: {
        "url": pathManut,
        "type": "POST",
        'data' : {
            'filterStatus': $('#filterStatus').val()
        },
    },
    'drawCallback': function() {
        overrideSearch($('#tableManutention_id_filter input'), tableManutention);
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis' },
        { "data": 'Date demande', 'name': 'Date demande', 'title': 'Date demande' },
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé' },
        { "data": 'Date souhaitée', 'name': 'Date souhaitée', 'title': 'Date souhaitée' },
        { "data": 'Date de réalisation', 'name': 'Date de réalisation', 'title': 'Date de réalisation' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
    ],
});

$(function() {
    initDateTimePicker();
    initSelect2($('.filter-select2[name="statut"]'), 'Statut');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
    registerDropdownPosition();
    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_MANUT);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
});

// filtres de recheches

let modalNewManutention = $("#modalNewManutention");
let submitNewManutention = $("#submitNewManutention");
let urlNewManutention = Routing.generate('manutention_new', true);
InitialiserModal(modalNewManutention, submitNewManutention, urlNewManutention, tableManutention);

let modalModifyManutention = $('#modalEditManutention');
let submitModifyManutention = $('#submitEditManutention');
let urlModifyManutention = Routing.generate('manutention_edit', true);
InitialiserModal(modalModifyManutention, submitModifyManutention, urlModifyManutention, tableManutention);

let modalDeleteManutention = $('#modalDeleteManutention');
let submitDeleteManutention = $('#submitDeleteManutention');
let urlDeleteManutention = Routing.generate('manutention_delete', true);
InitialiserModal(modalDeleteManutention, submitDeleteManutention, urlDeleteManutention, tableManutention);

let editorEditManutAlreadyDone = false;
function initEditManutEditor(modal) {
    if (!editorEditManutAlreadyDone) {
        initEditorInModal(modal);
        editorEditManutAlreadyDone = true;
    }
};

//initialisation editeur de texte une seule fois
let editorNewManutAlreadyDone = false;
function initNewManutentionEditor(modal) {
    if (!editorNewManutAlreadyDone) {
        initEditor('.editor-container-new');
        editorNewManutAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    if ($(button).hasClass('not-active')) {
        if ($("#statutManut").val() === "0") {
            $("#statutManut").val("1");
        } else {
            $("#statutManut").val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function toggleManutQuill() {
    let $modal = $('#modalEditManutention');
    let enable = $modal.find('#statut').val() === '1';
    toggleQuill($modal, enable);
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('manutention_index');
    }
}
