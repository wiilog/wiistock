$(document).ready(() => {
    const $statusSelector = $('.filterService select[name="statut"]');

    initDateTimePicker();
    Select2.init($statusSelector, 'Statuts');
    Select2.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2.user($('.filterService select[name="requesters"]'), "Demandeurs");
    Select2.user($('.filterService select[name="operators"]'), "Opérateurs");

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
        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_TRANSFER_REQUEST);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }

    const $modalNewTransferRequest = $('#modalNewTransfer');
    $modalNewTransferRequest.on('show.bs.modal', function () {
        clearModal("#modalNewTransfer");
        initNewTransferRequestEditor("#modalNewTransfer");
    });
});

$('.select2').select2();

let pathTransferOrder = Routing.generate('transfer_request_api', true);
let transferOrderTableConfig = {
    processing: true,
    serverSide: true,
    order: [[1, 'desc']],
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

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Création:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

//initialisation editeur de texte une seule fois à la création
let editorNewTransferRequestAlreadyDone = false;

function initNewTransferRequestEditor(modal) {
    if (!editorNewTransferRequestAlreadyDone) {
        initEditorInModal(modal);
        editorNewTransferRequestAlreadyDone = true;
    }

    Select2.location($('.ajax-autocomplete-location'))
}
