//initialisation editeur de texte une seule fois
let editorNewReceptionAlreadyDone = false;
let onFlyFormOpened = {};
let tableReception;

$(function () {
    $('.select2').select2();
    initDateTimePicker();
    initSelect2('#statut', 'Statut');

    $('.body-add-ref').css('display', 'none');

    const $submitSearchReception = $('#submitSearchReception');

    $submitSearchReception.on('click', function () {
        $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
        $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');
        let filters = {
            page: PAGE_RECEPTION,
            dateMin: $('#dateMin').val(),
            dateMax: $('#dateMax').val(),
            statut: $('#statut').val(),
            providers: $('#providers').select2('data'),
        };

        $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
        $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

        saveFilters(filters, tableReception);
    });

    ajaxAutoArticlesReceptionInit($('.select2-autocomplete-articles'));

    // RECEPTION
    let pathTableReception = Routing.generate('reception_api', true);
    tableReception = $('#tableReception_id').DataTable({
        serverSide: true,
        processing: true,
        order: [[1, "desc"]],
        "columnDefs": [
            {
                "type": "customDate",
                "targets": 1
            },
            {
                "orderable": false,
                "targets": 0
            }
        ],
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathTableReception,
            "type": "POST",
        },
        'drawCallback': function () {
            overrideSearch($('#tableReception_id_filter input'), tableReception);
        },
        columns: [
            {"data": 'Actions', 'title': 'Actions'},
            {"data": 'Date', 'title': 'Date création'},
            {"data": 'DateFin', 'title': 'Date fin'},
            {"data": 'Numéro de commande', 'title': 'Numéro commande'},
            {"data": 'Fournisseur', 'title': 'Fournisseur'},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'Statut', 'title': 'Statut'},
            {"data": 'Commentaire', 'title': 'Commentaire'},
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

    let modalReceptionNew = $("#modalNewReception");
    let SubmitNewReception = $("#submitReceptionButton");
    let urlReceptionIndex = Routing.generate('reception_new', true);
    InitialiserModal(modalReceptionNew, SubmitNewReception, urlReceptionIndex);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RECEPTION);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoFournisseurInit($('.filters').find('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
});

function initNewReceptionEditor(modal) {
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    if (!editorNewReceptionAlreadyDone) {
        initEditorInModal(modal);
        editorNewReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'));
    ajaxAutoCompleteTransporteurInit($(modal).find('.ajax-autocomplete-transporteur'));
    initDateTimePicker('#dateCommande, #dateAttendue');
    initDateTimePickerCL();
    $('.list-multiple').select2();
}

function initDateTimePickerCL() {
    $('.date-cl').each(function() {
        initDateTimePicker('#' + $(this).attr('id'));
    });
}

function initDateTimePickerReception() {
    initDateTimePicker('#dateCommande, #dateAttendue');
    initDateTimePickerCL();
}
