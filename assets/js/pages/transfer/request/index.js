import Routing from '@app/fos-routing';

$(() => {
    const $statusSelector = $('.filterService select[name="statut"]');

    initDateTimePicker();
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);

    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

    if (val && val.length > 0) {
        let valuesStr = val.split(',');
        let valuesInt = [];
        valuesStr.forEach((value) => {
            valuesInt.push(parseInt(value));
        })
        $statusSelector.val(valuesInt).select2();
    } else {
        getUserFiltersByPage(PAGE_TRANSFER_REQUEST);
    }

    const $modalNewTransferRequest = $('#modalNewTransfer');
    $modalNewTransferRequest.on('show.bs.modal', function () {
        clearModal("#modalNewTransfer");
        initNewTransferRequestEditor();
    });

    $('.select2').select2();

    let pathTransferOrder = Routing.generate('transfer_request_api', true);
    let transferOrderTableConfig = {
        processing: true,
        serverSide: true,
        order: [['number', 'desc']],
        ajax: {
            "url": pathTransferOrder,
            "type": "POST",
            'data' : {
                'filterStatus': $('#filterStatus').val()
            },
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {"data": 'actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'number', 'name': 'Numéro', 'title': 'Numéro'},
            {"data": 'status', 'name': 'Statut', 'title': 'Statut'},
            {"data": 'requester', 'name': 'Demandeur', 'title': 'Demandeur'},
            {"data": 'origin', 'name': 'Origine', 'title': 'Origine'},
            {"data": 'destination', 'name': 'Destination', 'title': 'Destination'},
            {"data": 'creationDate', 'name': 'Création', 'title': 'Date de création'},
            {"data": 'validationDate', 'name': 'Validation', 'title': 'Date de validation'},
        ]
    };
    let table = initDataTable('tableTransferRequest', transferOrderTableConfig);


    let modalNewTransferRequest = $("#modalNewTransfer");
    let SubmitNewTransferRequest = $("#submitNewTransfer");
    let urlNewTransferRequest = Routing.generate('transfer_request_new', true)
    InitModal(modalNewTransferRequest, SubmitNewTransferRequest, urlNewTransferRequest, {tables: [table]});

    initSearchDate(table, 'Création');

});

function initNewTransferRequestEditor() {
    Select2Old.location($('.ajax-autocomplete-location'))
}
