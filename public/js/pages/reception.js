//initialisation editeur de texte une seule fois
let editorNewReceptionAlreadyDone = false;
let onFlyFormOpened = {};
let tableReception;

$(function () {
    $('.select2').select2();
    initDateTimePicker();
    Select2Old.init($('#statut'), 'Statuts');
    Select2Old.initFree($('.select2-free'), 'N° de commande');
    initOnTheFlyCopies($('.copyOnTheFly'));
    Select2Old.user($('.filters .ajax-autocomplete-user'), 'Destinataire(s)');

    // RECEPTION
    let pathTableReception = Routing.generate('reception_api', true);
    let tableReceptionConfig = {
        serverSide: true,
        processing: true,
        order: [['Date', "desc"]],
        ajax: {
            "url": pathTableReception,
            "type": "POST",
            'data': {
                'purchaseRequestFilter': $('#purchaseRequest').val()
            }
        },
        drawConfig: {
            needsSearchOverride: true,
            needsColumnHide: true,
        },
        columns: [
            {"data": 'Actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Date', 'name': 'date', 'title': 'Date création'},
            {"data": 'number', 'name': 'number', 'title': 'réception.n° de réception', translated: true},
            {"data": 'dateAttendue', 'name': 'dateAttendue', 'title': 'Date attendue'},
            {"data": 'DateFin', 'name': 'dateFin', 'title': 'Date fin'},
            {"data": 'orderNumber', 'name': 'orderNumber', 'title': 'Numéro commande'},
            {"data": 'receiver', 'name': 'receiver', 'title': 'Destinataire(s)', orderable: false},
            {"data": 'Fournisseur', 'name': 'fournisseur', 'title': 'Fournisseur'},
            {"data": 'Statut', 'name': 'statut', 'title': 'Statut'},
            {"data": 'storageLocation', 'name': 'storageLocation', 'title': 'Emplacement de stockage'},
            {"data": 'Commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
            {"data": 'emergency', 'name': 'emergency', 'title': 'urgence', visible: false},
        ],
        rowConfig: {
            needsColor: true,
            color: 'danger',
            needsRowClickAction: true,
            dataToCheck: 'emergency'
        }
    };
    tableReception = initDataTable('tableReception_id', tableReceptionConfig);

    let $modalReceptionNew = $("#modalNewReception");
    let $submitNewReception = $("#submitReceptionButton");
    let urlReceptionIndex = Routing.generate('reception_new', true);
    InitModal($modalReceptionNew, $submitNewReception, urlReceptionIndex);

    // filtres enregistrés en base pour chaque utilisateur
    if ($('#purchaseRequestFilter').val() !== '0') {
        const purchaseRequestFilter = $('#purchaseRequestFilter').val().split(',');
        purchaseRequestFilter.forEach(function (filter) {
            let option = new Option(filter, filter, true, true);
            $('#commandList').append(option).trigger('change');
        })
    } else {
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_RECEPTION);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }

    Select2Old.provider($('.filters').find('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
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
    Select2Old.provider($('.ajax-autocomplete-fournisseur'));
    Select2Old.location($('.ajax-autocomplete-location'));
    Select2Old.carrier($modal.find('.ajax-autocomplete-transporteur'));
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
