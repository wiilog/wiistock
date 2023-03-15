import AJAX, {GET, POST} from "@app/ajax";
import EditableDatatable, {MODE_CLICK_EDIT_AND_ADD, SAVE_MANUALLY} from "@app/editatable";

global.newTruckArrival = newTruckArrival;

$(function () {
    Select2Old.init($('.filters select[name="carriers"]'), 'Transporteurs');
    initDateTimePicker('#dateMin, #dateMax');

    const filters = JSON.parse($(`#truck-arrival-filters`).val())
    displayFiltersSup(filters);

    initTruckArrivalTable();
    newTruckArrival();
    const $modalNew = $('#newTruckArrivalModal');

    Form
        .create($modalNew)
        .addProcessor((data, errors, $form) => {
            let arrivalLines = [];
            $form.find('.table-truck-article-line tr').each(function () {
                const $line = $(this);
                if($line.find('td .wii-icon-trash').length > 0) {
                    arrivalLines.push({
                        trackingLinesNumber: $line.find('input[name="trackingLinesNumber"]').val(),
                        hasQualityReserve: $line.find('input[name="hasQualityReserve"]').is(':checked'),
                        attachments: $line.find('input[type="file"]').val(),
                        comment: $line.find('input[name="comment"]').val(),
                    });
                }
            });
            data.append('arrivalLines', JSON.stringify(arrivalLines));
        })
        .submitTo(POST, 'truck_arrival_new');

    $modalNew.on('change','.display-condition', function () {
        const checked = $(this).is(':checked');
        $(this).closest('.row').find('.displayed-on-checkbox').display(!checked);
        $(this).closest('tr').find('.enabled-on-checkbox button,.enabled-on-checkbox input').attr('disabled', !checked);
    })
    $modalNew.find('.display-condition').trigger('change');

    const $tableLines = $modalNew.find('.table-truck-article-line');
    EditableDatatable.create($tableLines, {
        mode: MODE_CLICK_EDIT_AND_ADD,
        save: SAVE_MANUALLY,
        needsPagingHide: true,
        columns: generateTruckArrivalLineTableColumns(),
        form: JSON.parse($modalNew.find('input#truck-arrival-line-form').val()),
        onDeleteRow: (datatable, event, row) => {
            updateTotalTrackingNumber(datatable, $modalNew);
        },
        onAddRow: (datatable) => {
            updateTotalTrackingNumber(datatable, $modalNew);
        }
    });
});

function updateTotalTrackingNumber(datatable, $modal) {
    $modal.find('#totalTrackingNumber').html(datatable.element.find('tr .wii-icon-trash').length);
}
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
    $modal.modal('show');
}
function generateTruckArrivalLineTableColumns() {
    return [
        {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder actions', orderable: false},
        {data: `trackingLinesNumber`, title: `N° tracking transporteur`},
        {data: `hasQualityReserve`, title: `Réserve qualité`},
        {data: `attachment`, title: `Pièce jointe` , className: 'enabled-on-checkbox'},
        {data: `comment`, title: `Commentaire` , className: 'enabled-on-checkbox'},
    ];
}
