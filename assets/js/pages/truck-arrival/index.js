import AJAX, {GET, POST} from "@app/ajax";
import EditableDatatable, {MODE_CLICK_EDIT_AND_ADD, SAVE_MANUALLY} from "@app/editatable";
import {initTrackingNumberSelect} from "@app/pages/truck-arrival/common";

global.newTruckArrival = newTruckArrival;

$(function () {
    Select2Old.init($('.filters select[name="carriers"]'), 'Transporteurs');
    initDateTimePicker('#dateMin, #dateMax');

    const filters = JSON.parse($(`#truck-arrival-filters`).val())
    displayFiltersSup(filters);

    initTruckArrivalTable();
    const $modalNew = $('#newTruckArrivalModal');

    Form
        .create($modalNew)
        .submitTo(POST, 'truck_arrival_form_submit', {
            table: () => $('#truckArrivalsTable').DataTable()
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
        $warningMessage.find('.min-length').text(minTrackingNumberLength);
        $warningMessage.find('.max-length').text(maxTrackingNumberLength);
        initTrackingNumberSelect($trackingNumberSelect, $warningMessage ,minTrackingNumberLength ,maxTrackingNumberLength);
        $trackingNumberSelect.trigger('change');
    });
    $trackingNumberSelect.off('change').on('change', function () {
        $modalNew.find('#totalTrackingNumbers').html($(this).find('option:selected').length);
    })
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
                drawConfig: {
                    needsSearchOverride: true,
                },
                ajax: {
                    "url": pathTruckArrivalList,
                    "type": GET,
                },
                hideColumnConfig: {
                    columns: [
                        {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
                        ...columns,
                    ],
                    tableFilter: 'truckArrivalsTable',
                },
                columns: [
                    {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
                    ...columns,
                ],
            };
            initDataTable($table, tableTruckArrivalConfig);
            const queryParams = GetRequestQuery();

            if (queryParams.unassociated) {
                $('input[name=carrierTrackingNumberNotAssigned]').prop('checked', true);
                $('.filters-submit').click();
            }
            SetRequestQuery({});
        });
}

function newTruckArrival() {
    const $modal = $('#newTruckArrivalModal');
    clearModal($modal);
    $modal.find('.display-condition').trigger('change');
    $modal.modal('show');
}
