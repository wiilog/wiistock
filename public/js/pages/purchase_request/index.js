$(function() {
    const $statusSelector = $('.filterService select[name="statut"]');

    initPageDataTable();

    initDateTimePicker();
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

function initPageDataTable() {
    let pathPurchaseRequest = Routing.generate('purchase_request_api', true);
    let purchaseRequestTableConfig = {
        processing: true,
        serverSide: true,
        ajax: {
            "url": pathPurchaseRequest,
            "type": "POST",
            'data' : {
                'filterStatus': $('#filterStatus').val(),
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
            {"data": 'validationDate', 'name': 'Validation', 'title': 'Date de validation'},
            {"data": 'considerationDate', 'name': 'Prise en compte', 'title': 'Date de prise en compte'},
            {"data": 'processingDate', 'name': 'Traitement', 'title': 'Date de traitement'},
            {"data": 'requester', 'name': 'Demandeur', 'title': 'Demandeur'},
            {"data": 'status', 'name': 'Statut', 'title': 'Statut'},
            {"data": 'buyer', 'name': 'Acheteur', 'title': 'Acheteur'},
            {"data": 'supplier', 'name': 'Fournisseur', 'title': 'Fournisseur'},
        ]
    };
    return initDataTable('tablePurchaseRequest', purchaseRequestTableConfig);
}
