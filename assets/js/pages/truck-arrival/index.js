import AJAX, {GET, POST} from "@app/ajax";

global.editTruckArrival = editTruckArrival;
global.newTruckArrival = newTruckArrival;

$(function () {
    Select2Old.init($('.filters select[name="carriers"]'), 'Transporteurs');
    initDateTimePicker('#dateMin, #dateMax');

    const filters = JSON.parse($(`#truck-arrival-filters`).val())
    displayFiltersSup(filters);

    initTruckArrivalTable();
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
                    dataToCheck: 'delay'
                },
                drawConfig: {
                    needsSearchOverride: true,
                },
                ajax: {
                    "url": pathTruckArrivalList,
                    "type": GET,
                    'data': {
                        // TODO : add filters
                    },
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
        });
}

function newTruckArrival() {
    const $modal = $('#newTruckArrivalModal');
    $modal.modal('show');
}

function editTruckArrival(id) {
    const $modal = $('#editTruckArrivalModal');
    $modal.modal('show');
}

