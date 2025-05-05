import AJAX, {GET, POST} from "@app/ajax";
import Routing from '@app/fos-routing';
import {initTrackingNumberSelect, setTrackingNumberWarningMessage} from "@app/pages/truck-arrival/common";
import {initDataTable} from "@app/datatable";
import {getUserFiltersByPage} from "@app/utils";

global.newTruckArrival = newTruckArrival;

$(function () {
    initDateTimePicker('#dateMin, #dateMax');

    const requestQuery = GetRequestQuery();
    if (!requestQuery.dashboardcomponentid) {
        getUserFiltersByPage(PAGE_TRUCK_ARRIVAL);
    }

    initTruckArrivalTable();
    const $modalNew = $('#newTruckArrivalModal');

    Form
        .create($modalNew)
        .submitTo(POST, 'truck_arrival_form_submit', {
            success: (response) => {
                if(response.redirect){
                    window.location.href = response.redirect;
                } else {
                    printTruckArrivalLabel(null, response.truckArrivalId);
                }
            },
            tables: () => $('#truckArrivalsTable').DataTable()
        });

    $modalNew.on('change','.display-condition', function () {
        const checked = $(this).is(':checked');
        $(this).closest('.row').find('.displayed-on-checkbox').display(!checked);
        $(this).closest('tr').find('.enabled-on-checkbox button,.enabled-on-checkbox input').attr('disabled', !checked);
    })
    $modalNew.find('.display-condition').trigger('change');

    const $trackingNumberSelect = $modalNew.find('select[name="trackingNumbers"]');
    let $warningMessage = $trackingNumberSelect.closest('.form-group').find('.warning-message');
    $modalNew.find('select[name="carrier"]').on('change', function () {
        let data = $(this).select2('data')[0] || {};
        let minTrackingNumberLength = data.minTrackingNumberLength;
        let maxTrackingNumberLength = data.maxTrackingNumberLength;
        initTrackingNumberSelect($trackingNumberSelect, $warningMessage ,minTrackingNumberLength ,maxTrackingNumberLength);
        $trackingNumberSelect.trigger('change');
    });
    $trackingNumberSelect.off('change').on('change', function () {
        $modalNew.find('#totalTrackingNumbers').html($(this).find('option:selected').length);
    });

    $modalNew.find('.go-to-arrival').on('click', function() {
        $(this).val(true);
    });
});

function initTruckArrivalTable() {
    AJAX
        .route(GET, 'truck_arrival_api_columns')
        .json()
        .then((columns) => {
            let pathTruckArrivalList = Routing.generate('truck_arrival_api_list', true);
            let $table = $('#truckArrivalsTable');
            let tableTruckArrivalConfig = {
                serverSide: true,
                processing: true,
                page: `truck-arrival`,
                order: [['creationDate', 'desc']],
                rowConfig: {
                    needsRowClickAction: true,
                    needsColor: true,
                    color: 'danger',
                    dataToCheck: 'late'
                },
                ajax: {
                    "url": pathTruckArrivalList,
                    "type": POST,
                    'data': {
                        fromDashboard: $('[name="fromDashboard"]').val(),
                        carrierTrackingNumberNotAssigned: $('input[name=carrierTrackingNumberNotAssigned]').val(),
                        locations: $('[name="unloadingLocation"]').val(),
                        countNoLinkedTruckArrival: $('[name="countNoLinkedTruckArrival"]').val(),
                    },
                },
                columns: [
                    {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
                    ...columns,
                ],
            };
            initDataTable($table, tableTruckArrivalConfig);
            SetRequestQuery({});
        });
}

function newTruckArrival() {
    const $modal = $('#newTruckArrivalModal');
    clearModal($modal);
    $modal.find('.display-condition').trigger('change');
    $modal.modal('show');
}
