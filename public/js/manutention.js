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
    ajax: {
        "url": pathManut,
        "type": "POST",
        'data' : {
            'filterStatus': $('#statut').val()
        },
    },
    'drawCallback': function() {
        overrideSearch($('#tableManutention_id_filter input'), tableManutention);
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
        { "data": 'Date demande', 'name': 'Date demande', 'title': 'Date demande' },
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé' },
        { "data": 'Date souhaitée', 'name': 'Date souhaitée', 'title': 'Date souhaitée' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
    ],
});

$submitSearchManut.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');
    let filters = {
        page: PAGE_MANUT,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        users: $('#utilisateur').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableManutention);

    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('manutention_index');
    }
});

// applique les filtres si pré-remplis
$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Statut');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');

    let val = $('#statut').val();
    if (!val) {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_MANUT);
        $.post(path, params, function (data) {
            data.forEach(function (element) {
                if (element.field == 'utilisateurs') {
                    let values = element.value.split(',');
                    let $utilisateur = $('#utilisateur');
                    values.forEach((value) => {
                        let valueArray = value.split(':');
                        let id = valueArray[0];
                        let username = valueArray[1];
                        let option = new Option(username, id, true, true);
                        $utilisateur.append(option).trigger('change');
                    });
                } else if (element.field == 'dateMin' || element.field == 'dateMax') {
                    $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
                } else {
                    $('#' + element.field).val(element.value);
                }
            });
        }, 'json');
    }
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
        if ($("#s").val() == "0") {
            $("#s").val("1");
        } else {
            $("#s").val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

$submitSearchManut.on('keypress', function(e) {
    if (e.which === 13) {
        $submitSearchManut.click();
    }
});

function toggleManutQuill() {
    let $modal = $('#modalEditManutention');
    let enable = $modal.find('#statut').val() === '1';
    toggleQuill($modal, enable);
}

