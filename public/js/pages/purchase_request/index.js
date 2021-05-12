$(document).ready(() => {
    const $statusSelector = $('.filterService select[name="statut"]');

    initDateTimePicker();
    Select2Old.init($statusSelector, 'Statuts');
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2Old.user($('.filterService select[name="requesters"]'), "Demandeurs");
    Select2Old.user($('.filterService select[name="buyers"]'), "Acheteurs");

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
        let params = JSON.stringify(PAGE_PURCHASE_REQUEST);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});

$('.select2').select2();

let pathPurchaseRequest = Routing.generate('purchase_request_api', true);
let purchaseRequestTableConfig = {
    processing: true,
    serverSide: true,
    order: [['number', 'desc']],
    ajax: {
        "url": pathPurchaseRequest,
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
        {"data": 'creationDate', 'name': 'Création', 'title': 'Date de création'},
        {"data": 'considerationDate', 'name': 'Prise en compte', 'title': 'Date de prise en compte'},
        {"data": 'validationDate', 'name': 'Validation', 'title': 'Date de validation'},
        {"data": 'requester', 'name': 'Demandeur', 'title': 'Demandeur'},
        {"data": 'status', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'buyer', 'name': 'Acheteur', 'title': 'Acheteur'},

    ]
};
let table = initDataTable('tablePurchaseRequest', purchaseRequestTableConfig);

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
let editorNewPurchaseRequestAlreadyDone = false;

function initNewTransferRequestEditor(modal) {
    if (!editorNewPurchaseRequestAlreadyDone) {
        initEditorInModal(modal);
        editorNewPurchaseRequestAlreadyDone = true;
    }

    Select2Old.location($('.ajax-autocomplete-location'))
}
