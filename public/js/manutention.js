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

    let filters = {
        page: PAGE_MANUT,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        users: $('#utilisateur').select2('data'),
    };

    saveFilters(filters, tableManutention);
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableManutention.column('Date:name').index();

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
    tableManutention
        .columns('Demandeur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    // affichage par défaut du filtre select2 demandeur = utilisateur courant
    $('#utilisateur').val(demandeur).trigger('change');
}

// applique les filtres si pré-remplis
$(function() {
    let val = $('#statut').val();
    if (val != null && val != '') {
        $submitSearchManut.click();
    }

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MANUT);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0) $submitSearchManut.click();
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
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

