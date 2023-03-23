import {initReserveForm} from "@app/pages/truck-arrival/reserve";
import {POST} from "@app/ajax";
import {initTrackingNumberSelect} from "@app/pages/truck-arrival/common";

global.newTrackingNumber = newTrackingNumber;
global.editTruckArrival = editTruckArrival;
global.deleteTruckArrivalLine = deleteTruckArrivalLine;

const $modalEdit = $('#editTruckArrivalModal');

let truckArrival;
let truckArrivalLinesTable;

$(function () {
    Form
        .create($modalEdit)
        .submitTo(POST, 'truck_arrival_form_submit');

    const $reserveModals = $('.reserveModal');
    initReserveForm($reserveModals)

    $reserveModals.each(function (index , $modal) {
        Form
            .create($modal)
            .submitTo( POST, 'reserve_form_submit', {success: () => {
                    location.reload();
                }
            })
    });

    truckArrival = $('[name=truckArrival]').val();
    truckArrivalLinesTable = initTruckArrivalLinesTable();
});


function initTruckArrivalLinesTable() {
    return initDataTable(`truckArrivalLinesTable`, {
        processing: true,
        serverSide: true,
        paging: true,
        order: [[`lineNumber`, `desc`]],
        ajax: {
            url: Routing.generate(`truck_arrival_lines_api`, {truckArrival}, true),
            type: `GET`
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `lineNumber`, title: `N° tracking transporteur`},
            {data: `associatedToUL`, title: `Associé à un arrivage UL`},
            {data: `arrivalLinks`, title: `Lien(s) arrivage UL`},
            {data: `operator`, title: `Opérateur`},
        ],
        rowConfig: {
            needsRowClickAction: false,
        },
        drawConfig: {
            needsSearchOverride: true
        }
    });
}

function deleteTruckArrivalLine(deleteButton){
    AJAX.route(AJAX.POST, 'truck_arrival_lines_delete', {
        truckArrivalLineId: deleteButton.data('id'),
    })
        .json()
        .then(() => {
            truckArrivalLinesTable.ajax.reload();
        });
}

function editTruckArrival(id) {
    Modal.load('truck_arrival_form_edit', {id}, $modalEdit);
}

function newTrackingNumber() {
    const $modal = $('#newTrackingNumberModal');
    let truckArrivalId = $modal.find('input[name=truckArrival]').val();
    Form
        .create($modal)
        .onOpen(()=> {
            $modal.find('select[name="trackingNumbers"]').empty();
        })
        .submitTo( POST, 'add_tracking_number', {
            success: () => {
                truckArrivalLinesTable.ajax.reload();
            }
        });
    let minTrackingNumberLength = $('input[name=minTrackingNumber]').val();
    let maxTrackingNumberLength = $('input[name=maxTrackingNumber]').val();

    const $trackingNumberSelect = $modal.find('select[name="trackingNumbers"]');
    let $warningMessage = $trackingNumberSelect.closest('.form-group').find('.warning-message');
    $warningMessage.find('.min-length').text(minTrackingNumberLength);
    $warningMessage.find('.max-length').text(maxTrackingNumberLength);
    initTrackingNumberSelect($trackingNumberSelect, $warningMessage ,minTrackingNumberLength ,maxTrackingNumberLength);

    $modal.modal('show');
}
