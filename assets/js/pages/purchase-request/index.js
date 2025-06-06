import Form from "@app/form";
import {POST} from "@app/ajax";
import Routing from '@app/fos-routing';
import {getUserFiltersByPage} from '@app/utils';
import {onStatusChange} from '@app/pages/purchase-request/common';
import {initDataTable} from "@app/datatable";

global.onStatusChange = onStatusChange;

$(function() {
    const $statusSelector = $('.filterService select[name="statut"]');

    const purchaseRequestTable = initPageDataTable();

    initDateTimePicker();
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    Select2Old.user($('.filterService select[name="requesters"]'), "Demandeurs");
    Select2Old.user($('.filterService select[name="buyers"]'), "Acheteurs");
    Select2Old.provider($('.select-filter select[name="providers"]'), "Fournisseurs");

    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

    if (val && val.length > 0) {
        let valuesStr = val.split(',');
        let valuesInt = valuesStr.map((value) => parseInt(value));

        $statusSelector.val(valuesInt).select2();
    } else {
        getUserFiltersByPage(PAGE_PURCHASE_REQUEST);
    }

    const $modalNewPurchaseRequest = $('#modalNewPurchaseRequest');

    Form
        .create($modalNewPurchaseRequest, {resetView: ['open', 'close']})
        .onOpen(() => {
            $modalNewPurchaseRequest.find('[name="status"]').trigger('change');
        })
        .submitTo(POST, 'purchase_request_new', {
            success: ({redirect}) => {
                window.location.href = redirect;
            }
        });
});

function initPageDataTable() {
    let pathPurchaseRequest = Routing.generate('purchase_request_api', true);
    let purchaseRequestTableConfig = {
        processing: true,
        serverSide: true,
        ajax: {
            "url": pathPurchaseRequest,
            "type": POST,
            'data' : {
                'filterStatus': $('#filterStatus').val(),
            },
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        order: [['creationDate', 'desc']],
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
            {"data": 'deliveryFee', 'name': 'Frais de livraison', 'title': 'Frais de livraison'},
        ]
    };
    return initDataTable('tablePurchaseRequest', purchaseRequestTableConfig);
}
