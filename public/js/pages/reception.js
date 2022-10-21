let onFlyFormOpened = {};
let receptionsTable;

$(function () {
    // RECEPTION
    initTableReception();
    $('.select2').select2();
    initDateTimePicker();
    Select2Old.initFree($('.select2-free'), 'N° de commande');
    initOnTheFlyCopies($('.copyOnTheFly'));
    Select2Old.user($('.filters .ajax-autocomplete-user'), 'Destinataire(s)');


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

function initTableReception() {
    let pathReception = Routing.generate('reception_api', true);
    return $
        .post(Routing.generate('reception_api_columns'))
        .then((columns) => {
            let tableReceptionConfig = {
                serverSide: true,
                processing: true,
                order: [['Date', "desc"]],
                ajax: {
                    "url": pathReception,
                    "type": "POST",
                    'data': {
                        'purchaseRequestFilter': $('#purchaseRequest').val()
                    }
                },
                columns,
                drawConfig: {
                    needsSearchOverride: true,
                    needsColumnHide: true,
                },
                rowConfig: {
                    needsColor: true,
                    color: 'danger',
                    needsRowClickAction: true,
                    dataToCheck: 'emergency'
                },
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableReception_id'
                },
            };

            receptionsTable = initDataTable('tableReception_id', tableReceptionConfig);
            receptionsTable.on('responsive-resize', function () {
                resizeTable(receptionsTable);
            });
            return receptionsTable;
        });
}
