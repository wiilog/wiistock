import AJAX, {GET, POST} from "@app/ajax";
import EditableDatatable, {MODE_CLICK_EDIT_AND_ADD, MODE_EDIT, MODE_NO_EDIT, SAVE_MANUALLY} from "@app/editatable";

global.editTruckArrival = editTruckArrival;
global.newTruckArrival = newTruckArrival;

$(function () {
    Select2Old.init($('.filters select[name="carriers"]'), 'Transporteurs');
    initDateTimePicker('#dateMin, #dateMax');

    const filters = JSON.parse($(`#truck-arrival-filters`).val())

    displayFiltersSup(filters);

    initTruckArrivalTable();
    newTruckArrival();
    const $modalNew = $('#newTruckArrivalModal');

    EditableDatatable.create(`.table-truck-article-line`, {
        mode: MODE_CLICK_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        needsPagingHide: true,
        columns: generateTruckArrivalLineTableColumns(),
        form: JSON.parse($modalNew.find('input#truck-arrival-line-form').val()),
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
                    dataToCheck: 'delay'
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
        });
}

function newTruckArrival() {
    const $modal = $('#newTruckArrivalModal');

    $modal.find('.display-condition').on('change', function () {
        const checked = $(this).is(':checked');
        $(this).closest('.row').find('.disabled-on-checkbox').display(!checked);
    }).trigger('change');

    $modal.modal('show');
}

function editTruckArrival(id) {
    const $modal = $('#editTruckArrivalModal');
    $modal.modal('show');
}

function generateTruckArrivalLineTableColumns() {
    return [
        {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
        {data: `trackingLinesNumber`, title: `N° tracking transporteur`},
        {data: `isQualityReseve`, title: `Réserve qualité`},
        {data: `attachment`, title: `Pièce jointe`},
        {data: `comment`, title: `Commentaire`},
    ];
}
