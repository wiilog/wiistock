//initialisation editeur de texte une seule fois
let editorNewReceptionAlreadyDone = false;
let onFlyFormOpened = {};
let tableReception;

$(function () {
    $('.select2').select2();
    initDateTimePicker();
    initSelect2($('#statut'), 'Statut');
    initOnTheFlyCopies($('.copyOnTheFly'));
    $('.body-add-ref').css('display', 'none');

    ajaxAutoArticlesReceptionInit($('.select2-autocomplete-articles'));

    // RECEPTION
    let pathTableReception = Routing.generate('reception_api', true);
    tableReception = $('#tableReception_id').DataTable({
        serverSide: true,
        processing: true,
        order: [[8, "desc"], [1, "desc"]],
        "columnDefs": [
            {
                "orderable": false,
                "targets": 0
            },
            {
                "targets": 8,
                "visible": false
            },
        ],
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathTableReception,
            "type": "POST",
        },
        drawCallback: function (resp) {
            overrideSearch($('#tableReception_id_filter input'), tableReception);
            hideColumns(tableReception, resp.json.columnsToHide);
        },
        headerCallback: function(thead) {
            $(thead).find('th').eq(5).attr('title', "n° de réception");
        },
        columns: [
            {"data": 'Actions', 'name': 'actions', 'title': '', className: 'noVis'},
            {"data": 'Date', 'name': 'date', 'title': 'Date création'},
            {"data": 'DateFin', 'name': 'dateFin', 'title': 'Date fin'},
            {"data": 'Numéro de commande', 'name': 'numCommande', 'title': 'Numéro commande'},
            {"data": 'Fournisseur', 'name': 'fournisseur', 'title': 'Fournisseur'},
            {"data": 'Référence', 'name': 'reference', 'title': $('#noReception').val()},
            {"data": 'Statut', 'name': 'statut', 'title': 'Statut'},
            {"data": 'Commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence'},
        ],
        rowCallback: function (row, data) {
            $(row).addClass(data.urgence ? 'table-danger' : '');
            initActionOnRow(row);
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
    let $modal = $(modal);
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    if (!editorNewReceptionAlreadyDone) {
        initEditorInModal(modal);
        editorNewReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'));
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-location'));
    ajaxAutoCompleteTransporteurInit($modal.find('.ajax-autocomplete-transporteur'));
    initDateTimePicker('#dateCommande, #dateAttendue');

    $('.date-cl').each(function() {
        initDateTimePicker('#' + $(this).attr('id'));
    });

    $modal.find('.list-multiple').select2();
}

function initReceptionLocation() {
    // initialise valeur champs select2 ajax
    let $receptionLocationSelect = $('#receptionLocation');
    let dataReceptionLocation = $('#receptionLocationValue').data();
    if (dataReceptionLocation.id && dataReceptionLocation.text) {
        let option = new Option(dataReceptionLocation.text, dataReceptionLocation.id, true, true);
        $receptionLocationSelect.append(option).trigger('change');
    }
}
