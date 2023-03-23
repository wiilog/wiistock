import {initReserveForm} from "@app/pages/truck-arrival/reserve";
import {POST} from "@app/ajax";
import AJAX from "@app/ajax";
import {initTrackingNumberSelect} from "@app/pages/truck-arrival/common";

global.newTrackingNumber = newTrackingNumber;
global.editTruckArrival = editTruckArrival;
global.deleteTruckArrivalLine = deleteTruckArrivalLine;
global.deleteTruckArrivalLineReserve = deleteTruckArrivalLineReserve;
global.openModalQualityReserveContent = openModalQualityReserveContent;

const $modalEdit = $('#editTruckArrivalModal');
const $modalReserveQuality = $('#modalReserveQuality');

let truckArrival;
let truckArrivalLinesTable;
let truckArrivalLinesQualityReservesTable;



$(function () {
    Form
        .create($modalEdit)
        .submitTo(POST, 'truck_arrival_form_submit', {
            success: () => {
                window.location.reload();
            }
        });

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
    truckArrivalLinesQualityReservesTable = initTruckArrivalLineQualityReservesTable();

    let modalDeleteTruckArrival = $('#modalDeleteTruckArrival');
    Form.create(modalDeleteTruckArrival).onSubmit((data, form) => {
        form.loading(() => {
            return AJAX
                .route(AJAX.POST, `truck_arrival_delete`, {truckArrival: $('#truckArrivalId').val()})
                .json(data)
                .then((response) => {
                    if (response.success ) {
                        window.location.href = response.redirect;
                    }
                })
        });
    });

    $('.new-quality-reserve-button').on('click', function(){
        openModalQualityReserveContent($modalReserveQuality);
    });
});

function openModalQualityReserveContent($modalReserveQuality, reserveId = null){
    $modalReserveQuality.find('.modal-body').empty();
    Form.create($modalReserveQuality, true)
        .onOpen(() => {
            AJAX.route(AJAX.POST, 'reserve_modal_quality_content', {
                reserveId,
                truckArrival
            })
            .json()
            .then((response) => {
                if(response.success){
                    $modalReserveQuality.find('.modal-body').append(response.content);
                }

            });
        })
        .submitTo(POST, 'reserve_form_submit', {
            reserveType: 'quality',
            reserveId,
            success: () => {
                truckArrivalLinesQualityReservesTable.ajax.reload();
            }
        });

    $modalReserveQuality.modal('show');
}

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
            {data: 'late', name: 'late', title: 'late', 'visible': false, 'searchable': false},
        ],
        rowConfig: {
            needsRowClickAction: false,
            needsColor: true,
            color: 'danger',
            dataToCheck: 'late'
        },
        drawConfig: {
            needsSearchOverride: true
        }
    });
}

function initTruckArrivalLineQualityReservesTable() {
    return initDataTable(`truckArrivalLinesQualityReservesTable`, {
        processing: true,
        serverSide: true,
        paging: false,
        searching: false,
        info: false,
        order: [[`reserveLineNumber`, `desc`]],
        ajax: {
            url: Routing.generate(`truck_arrival_lines_quality_reserves_api`, {truckArrival}, true),
            type: `GET`
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `reserveLineNumber`, title: `N° tracking transporteur`},
            {data: `attachment`, title: `Pièces jointes`, orderable: false},
            {data: `comment`, title: `Commentaire`, orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: true,
            callback: (row, data) => {
                //openModalQualityReserveContent($modalReserveQuality, data.id);
            }
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

function deleteTruckArrivalLineReserve(deleteButton){
    AJAX.route(AJAX.POST, 'truck_arrival_line_reserve_delete', {
        reserveId: deleteButton.data('id'),
    })
        .json()
        .then(() => {
            truckArrivalLinesQualityReservesTable.ajax.reload();
        });
}

function editTruckArrival(id) {
    Modal.load('truck_arrival_form_edit', {id}, $modalEdit);
}

function newTrackingNumber() {
    const $modal = $('#newTrackingNumberModal');
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
