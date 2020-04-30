$('.select2').select2();

let modalColumnVisible = $('#modalColumnVisibleArrivage');
let submitColumnVisible = $('#submitColumnVisibleArrivage');
let modalEditArrivage = $('#modalEditArrivage');
let submitEditArrivage = $('#submitEditArrivage');
let urlEditArrivage = Routing.generate('arrivage_edit', true);
let urlColumnVisible = Routing.generate('save_column_visible_for_arrivage', true);
let onFlyFormOpened = {};
let clicked = false;
let pageLength;

$(function () {
    initDateTimePicker('#dateMin, #dateMax, .date-cl');
    initSelect2($('#statut'), 'Statut');
    initSelect2($('#carriers'), 'Transporteurs');
    initOnTheFlyCopies($('.copyOnTheFly'));
    InitialiserModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);
    initModalWithAttachments(modalEditArrivage, submitEditArrivage, urlEditArrivage, tableArrivage);
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);
    registerDropdownPosition();
    $.post(path, params, function (data) {
        displayFiltersSup(data);
        initFilterDateToday();
    }, 'json');
    pageLength = Number($('#pageLengthForArrivage').val());
    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Destinataires');
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
    $('select[name="tableArrivages_length"]').on('change', function () {
        let newValue = Number($(this).val());
        if (newValue && newValue !== pageLength) {
            $.post(Routing.generate('update_user_page_length_for_arrivage'), JSON.stringify(newValue));
            pageLength = newValue;
        }
    });
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage;
let tableArrivageConfig = {
    serverSide: true,
    processing: true,
    pageLength: Number($('#pageLengthForArrivage').val()),
    order: [[1, "desc"]],
    scrollX: true,
    ajax: {
        "url": pathArrivage,
        "type": "POST",
        'data': {
            'clicked': () => clicked,
        },
        'dataSrc': function (json) {
            json.visible.forEach(element => {
                tableArrivage.column(element).visible(true);
            });
            return json.data;
        }
    },
    columns: [
        {"data": 'Actions', 'name': 'actions', 'title': ''},
        {"data": 'Date', 'name': 'date', 'title': 'Date'},
        {"data": "NumeroArrivage", 'name': 'numeroArrivage', 'title': $('#noArrTranslation').val()},
        {"data": 'Transporteur', 'name': 'transporteur', 'title': 'Transporteur'},
        {"data": 'Chauffeur', 'name': 'chauffeur', 'title': 'Chauffeur'},
        {"data": 'NoTracking', 'name': 'noTracking', 'title': 'N° tracking transporteur'},
        {"data": 'NumeroCommandeList', 'name': 'NumeroCommandeList', 'title': 'N° commande / BL'},
        {"data": 'Fournisseur', 'name': 'fournisseur', 'title': 'Fournisseur'},
        {"data": 'Destinataire', 'name': 'destinataire', 'title': $('#destinataireTranslation').val()},
        {"data": 'Acheteurs', 'name': 'acheteurs', 'title': $('#acheteursTranslation').val()},
        {"data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM'},
        {"data": 'Duty', 'name': 'duty', 'title': 'Douane'},
        {"data": 'Frozen', 'name': 'frozen', 'title': 'Congelé'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur'},
        {"data": 'Urgent', 'name': 'urgent', 'title': 'Urgent'},
        {"data": 'url', 'name': 'url', 'title': 'url', visible: false},
    ],
    columnDefs: [
        {
            targets: [0, 16],
            className: 'noVis'
        },
        {
            orderable: false,
            targets: [0]
        }
    ],
    headerCallback: function (thead) {
        $(thead).find('th').eq(2).attr('title', "n° d'arrivage");
        $(thead).find('th').eq(8).attr('title', "destinataire");
        $(thead).find('th').eq(9).attr('title', "acheteurs");
    },
    domConfig: {
        needsFullDomOverride: true
    },
    rowConfig: {
        needsDangerColor: true,
        needsRowClickAction: true,
        dataToCheck: 'urgent'
    },
    drawConfig: {
        needsSearchOverride: true,
        needsColumnHide: true,
        filterId: 'tableArrivages_filter'
    },
    buttons: [
        {
            extend: 'colvis',
            columns: ':not(.noVis)',
            className: 'd-none'
        },

    ],
    "lengthMenu": [10, 25, 50, 100],
};

tableArrivage = initDataTable('tableArrivages', tableArrivageConfig);
tableArrivage.on('responsive-resize', function (e, datatable) {
    datatable.columns.adjust().responsive.recalc();
});
function listColis(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_colis_api', true);
    let modal = $('#modalListColis');
    let params = {id: arrivageId};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}

let $modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
let redirectAfterArrival = $('#redirect').val();
initModalWithAttachments($modalNewArrivage, submitNewArrivage, urlNewArrivage, null, (params) => arrivalCallback(true, params, tableArrivage), redirectAfterArrival === 1, false);

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    let $modal = $(modal);
    clearModal($modal);
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    onFlyFormToggle('chauffeurDisplay', 'addChauffeur', true);
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
    initSelect2($modal.find('.ajax-autocomplete-fournisseur'));
    initSelect2($modal.find('.ajax-autocomplete-transporteur'));
    initSelect2($modal.find('.ajax-autocomplete-chauffeur'));
    initSelect2($modal.find('.ajax-autocomplete-user'), '', 1);
    $modal.find('.list-multiple').select2();
    initFreeSelect2($('.select2-free'));
}
