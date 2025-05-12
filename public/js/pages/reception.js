$(function () {
    // RECEPTION
    initTableReception();
    $('.select2').select2();
    initDateTimePicker();
    initOnTheFlyCopies($('.copyOnTheFly'));
    Select2Old.user($('.filters .ajax-autocomplete-user'), 'Destinataire(s)');

    let $modalReceptionNew = $("#modalNewReception");
    let $submitNewReception = $("#submitReceptionButton");
    let urlReceptionIndex = Routing.generate('reception_new', true);
    const query = GetRequestQuery();
    if (query["open-modal"] === "new") {
        $modalReceptionNew.on('hidden.bs.modal', function () {
            $modalReceptionNew.find("input[name='arrivage']").remove();
        });
    }
    InitModal($modalReceptionNew, $submitNewReception, urlReceptionIndex);
    $modalReceptionNew.on('shown.bs.modal', function () {
        Camera.init(
            $modalReceptionNew.find(`.take-picture-modal-button`),
            $modalReceptionNew.find(`[name="files[]"]`)
        );
    });
    if (query["open-modal"] === "new") {
        delete query['arrivage'];
        initNewReceptionEditor($modalReceptionNew);
    }

    const purchaseRequestFilter = $('[name="purchaseRequestFilter"]').val();
    if (purchaseRequestFilter !== '0') {
        const purchaseRequestFilter = purchaseRequestFilter.split(',');
        purchaseRequestFilter.forEach(function (filter) {
            let option = new Option(filter, filter, true, true);
            $('#commandList').append(option).trigger('change');
        })
    } else if (!$('[name="emergencyIdFilter"]').val())  {
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
    if (dataReceptionLocation && dataReceptionLocation.id && dataReceptionLocation.text) {
        let option = new Option(dataReceptionLocation.text, dataReceptionLocation.id, true, true);
        $receptionLocationSelect.append(option).trigger('change');
    }
}

function initTableReception() {
    let pathReception = Routing.generate('reception_api', true);
    const emergencyIdFilter = $('[name="emergencyIdFilter"]').val()
    return $
        .post(Routing.generate('reception_api_columns'))
        .then((columns) => {
            let tableReceptionConfig = {
                serverSide: true,
                processing: true,
                order: [['Date', "desc"]],
                ajax: {
                    "url": pathReception,
                    "type": POST,
                    'data': {
                        'purchaseRequestFilter': $('[name="purchaseRequest"]').val(),
                        ... emergencyIdFilter ? {emergencyId: emergencyIdFilter} : {}
                    }
                },
                columns,
                rowConfig: {
                    needsColor: true,
                    color: 'danger',
                    needsRowClickAction: true,
                    dataToCheck: 'emergency'
                },
            };

            return initDataTable('tableReception_id', tableReceptionConfig);
        });
}
