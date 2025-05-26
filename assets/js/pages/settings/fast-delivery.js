import Form from "@app/form";
import AJAX from "@app/ajax";
import Routing from '@app/fos-routing';
import {initDataTable} from "@app/datatable";

let deliveryStationTable;
let $modalNewDeliveryStationLine = $('#modalNewDeliveryStationLine');
let $modalDeleteDeliveryStationLine = $('#modalDeleteDeliveryStationLine');

global.openModalEditDeliveryStationLine = openModalEditDeliveryStationLine;
global.deleteDeliveryStationLine = deleteDeliveryStationLine;

$(function () {
    initDeliveryStationTable();
});

export function initializeFastDeliveryRequest($container) {
    $container.find('.add-row').on('click', function() {
        Form.create($modalNewDeliveryStationLine, {resetView: ['open', 'close']}).submitTo(`POST`, `delivery_station_line_new`, {
            tables: [deliveryStationTable],
        });

        $modalNewDeliveryStationLine.modal('show');
    });
}

function openModalEditDeliveryStationLine($modal, deliveryStationLineId){
    Form.create($modal)
        .clearOpenListeners()
        .onOpen(() => {
            AJAX.route(AJAX.POST, 'edit_delivery_station_line', {
                deliveryStationLineId,
            })
                .json()
                .then((response) => {
                    if (response.success) {
                        $modal.find('.modal-body').html(response.content);
                    }
                });
        })
        .submitTo(`POST`, `delivery_station_line_edit`, {
            tables: [deliveryStationTable],
        });
    $modal.modal('show');
}

function deleteDeliveryStationLine(deleteButton){
    Form.create($modalDeleteDeliveryStationLine)
        .submitTo(`POST`, `delivery_station_line_delete`, {
            tables: [deliveryStationTable],
        });

    $modalDeleteDeliveryStationLine.find('[type=submit]').val(deleteButton.data('id'));
    $modalDeleteDeliveryStationLine.modal('show');
}

function initDeliveryStationTable(){
    deliveryStationTable = initDataTable('deliveryStationLineTable', {
        serverSide: true,
        processing: true,
        ajax: {
            "url": Routing.generate('delivery_station_line_api', true),
            "type": "POST"
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
            {data: 'id', name: 'id', title: 'id', visible: false, orderable: false},
            {data: 'deliveryType', name: 'deliveryType', title: 'Type de livraison', orderable: false},
            {data: 'visibilityGroup', name: 'visibilityGroup', title: 'Groupe de visibilité', orderable: false},
            {data: 'destination', name: 'destination', title: 'Destination', orderable: false},
            {data: 'receivers', name: 'receivers', title: 'Utilisateur(s) mail information', orderable: false},
            {data: 'generatedExternalLink', name: 'generatedExternalLink', title: 'Lien externe généré', orderable: false, className: 'noVis'},
        ],
        domConfig: {
            needsPartialDomOverride: true,
        },
        paging: false,
        search: false,
    });
}
