import AJAX, {POST, GET} from "@app/ajax";
import Modal from "@app/modal";
import {initModalFormShippingRequest} from "@app/pages/shipping-request/form";

global.validateShippingRequest = validateShippingRequest;
global.openScheduledShippingRequestModal = openScheduledShippingRequestModal;
global.deleteExpectedLine = deleteExpectedLine;
let shippingId;

$(function() {
    shippingId = $('[name=shippingId]').val();


    const $modalEdit = $('#modalEditShippingRequest');
    initModalFormShippingRequest($modalEdit, 'shipping_request_edit', () => {
        $modalEdit.modal('hide');
        window.location.reload();
    });

    initScheduledShippingRequestForm();
    initShippingRequestExpectedLine();
    getShippingRequestStatusHistory();
});

function refreshTransportHeader(){
    AJAX.route(POST, 'get_transport_header_config', {
        id: shippingId
    })
        .json()
        .then(({detailsTransportConfig}) => {
            $('.transport-header').html(detailsTransportConfig);
        });
}
function validateShippingRequest(shipping_request_id){
    AJAX.route(GET, `shipping_request_validation`, {id:shipping_request_id})
        .json()
        .then((res) => {
            if (res.success) {
                location.reload()
            }
        });
}

function initScheduledShippingRequestForm(){
    let $modalScheduledShippingRequest = $('#modalScheduledShippingRequest');
    Form.create($modalScheduledShippingRequest).onSubmit((data, form) => {
        $modalScheduledShippingRequest.modal('hide');
        openPackingPack(data);
    });
}
function openPackingPack(dataShippingRequestForm){
    //todo WIIS-9591
}

function deleteExpectedLine($button, table) {
    const lineId = $button.data('id');
    if (lineId) {
        Modal.confirm({
            ajax: {
                method: AJAX.DELETE,
                route: 'shipping_request_expected_line_delete',
                params: { line: lineId },
            },
            message: 'Voulez-vous réellement supprimer cette ligne de référence ?',
            title: 'Supprimer le client',
            validateButton: {
                color: 'danger',
                label: 'Supprimer'
            },
            table,
        })
    }
    else {
        const row = table.row($button.closest(`tr`));
        row.remove();
        table.draw();
    }
}

function openScheduledShippingRequestModal($button){
    const id = $button.data('id')
    AJAX.route(GET, `check_expected_lines_data`, {id})
        .json()
        .then((res) => {
            if (res.success) {
                $('#modalScheduledShippingRequest').modal('show');
            }
        });
}

function initShippingRequestExpectedLine() {
    const $table = $('#expectedLinesTable');

    const table = initDataTable($table, {
        serverSide: false,
        ajax: {
            type: AJAX.GET,
            url: Routing.generate('api_shipping_request_expected_lines', {request: shippingId}, true),
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            removeInfo: true,
        },
        drawConfig: {
            needsColumnHide: true,
        },
        columns: [
            {data: 'actions', orderable: false, alwaysVisible: true, class: 'noVis', width: '10px'},
            {data: 'reference', title: 'Référence', required: true,},
            {data: 'information', orderable: false, alwaysVisible: true, class: 'noVis', width: '10px'},
            {data: 'editAction', orderable: false, alwaysVisible: true, class: 'noVis', width: '10px'},
            {data: 'label', title: 'Libellé'},
            {data: 'quantity', title: 'Quantité'},
            {data: 'price', title: 'Prix unitaire (€)', required: true,},
            {data: 'weight', title: 'Poids net (kg)', required: true,},
            {data: 'total', title: 'Montant Total',},
        ],
        ordering: false,
        paging: false,
        searching: false,
        scrollY: false,
        scrollX: true,
        drawCallback: () => {
            $(`#packTable_wrapper`).css(`overflow-x`, `scroll`);
            $(`.dataTables_scrollBody, .dataTables_scrollHead`)
                .css('overflow', 'visible')
                .css('overflow-y', 'visible');

            const $rows = $(table.rows().nodes());

            $rows.each(function () {
                const $row = $(this);
                const data = Form.process($row, {
                    ignoreErrors: true,
                });

                $row.data(`data`, JSON.stringify(data instanceof FormData ? data.asObject() : data));
            });

            $rows.off(`focusout.keyboardNavigation`).on(`focusout.keyboardNavigation`, function (event) {
                const $row = $(this);
                const $target = $(event.target);
                const $relatedTarget = $(event.relatedTarget);


                const wasLineSelect = $target.closest(`td`).find(`[name="reference"]`).exists();
                if ((event.relatedTarget && $.contains(this, event.relatedTarget))
                    || $relatedTarget.is(`button.delete-row`)
                    || wasLineSelect) {
                    return;
                }

                saveExpectedLine(shippingId, $row);
            });

            scrollToBottom();
            if (!$table.data('initialized')) {
                $table.data('initialized', true);
                // Resize table to avoid bug related to WIIS-8276,
                // timeout is necessary because drawCallback doesnt seem to be called when everything is fully loaded,
                // because we have some custom rendering actions which may take more time than native datatable rendering
                setTimeout(() => {
                    $table.DataTable().columns.adjust().draw();
                }, 500);
            }
        },
        createdRow: (row, data) => {
            // we display only + td on this line
            if (data && data.createRow) {
                const $row = $(row);
                const $tds = $row.children();
                const $tdAction = $tds.first();
                const $tdOther = $tds.slice(1);

                $tdAction
                    .attr('colspan', $tds.length)
                    .addClass('add-row');
                $row.find('span.add-row').removeClass('add-row');
                $tdOther.addClass('d-none');
            }
        },
    });

    scrollToBottom();
    $table.on(`keydown`, `[name="quantity"]`, function (event) {
        if (event.key === `.` || event.key === `,` || event.key === `-` || event.key === `+` || event.key === `e`) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    $table.on(`click`, `.add-row`, function () {
        addExpectedLineRow(table, $(this));
    });

    $table.on(`change`, `[name=referenceArticle]`, function (event) {
        const $reference = $(event.target);
        const [reference] = $reference.select2('data');

        const $row = $reference.closest('tr');
        const $labelWrapper = $row.find('.label-wrapper');
        $labelWrapper.text(reference?.label || '');

        if (reference) {
            $reference.select2('destroy');
            $reference.replaceWith(
                $('<span/>', {
                    html: [
                        $('<span/>', {text: reference.text}),
                        $('<input/>', {
                            type: 'hidden',
                            class: 'data',
                            value: reference.id,
                            name: 'referenceArticle',
                        }),
                    ]
                })
            );
            $row.find('.editAction')
                .removeClass('d-none')
                .attr('href', Routing.generate('reference_article_edit_page', {reference: reference.id}));

            if (reference.dangerous) {
                $row
                    .find('.dangerous')
                    .removeClass('d-none');
            }
        }

        table.columns.adjust();
    });

    $table.on(`change`, `[name=price], [name=quantity]`, function (event) {
        const $target = $(event.target);
        const $row = $target.closest('tr');
        const quantity = $row.find('[name=quantity]').val();
        const price = $row.find('[name=price]').val();
        const $total = $row.find('.total-wrapper');

        if (quantity && price) {
            $total.text(quantity * price);
        }
    });

    $table.on(`click`, `.delete-row`, function (event) {
        deleteExpectedLine($(this), table);
    });

    $table.on(`keydown`, function(event) {
        const tabulationKeyCode = 9;

        const $target = $(event.target);
        // check if input is the last of the row
        const lastInputOfRow = $target.is(
            $target
                .closest('tr')
                .find('.data')
                .last()
        );

        if (event.keyCode === tabulationKeyCode
            && lastInputOfRow) {
            event.preventDefault();
            event.stopPropagation();

            const $nextRow = $target.closest(`tr`).next();
            const $addRowButton = $nextRow.find(`.add-row`);
            if($addRowButton.exists()) {
                addExpectedLineRow(table, $addRowButton);
            }
        }
    });

    $(window).on(`beforeunload`, () =>  {
        const $focus = $(`tr :focus`);
        if($focus.exists()) {
            if(saveExpectedLine(shippingId, $focus.closest(`tr`))) {
                return true;
            }
        }
    });

    return table;
}

function saveExpectedLine(requestId, $row) {
    let data = Form.process($row);
    data = data instanceof FormData ? data.asObject() : data;

    if (data) {
        if (!jQuery.deepEquals(data, JSON.parse($row.data(`data`)))) {
            AJAX
                .route(AJAX.POST, `shipping_request_submit_changes_expected_lines`, {shippingRequest: requestId})
                .json(data)
                .then((response) => {
                    if (response.success) {
                        if (response.lineId) {
                            $row.find(`.delete-row`)
                                .attr('data-id', response.lineId)
                                .data('id', response.lineId);
                            $row.find('input[name="lineId"]').val(response.lineId);
                        }
                        $row
                            .data(`data`, JSON.stringify(data))
                            .attr(`data-data`, JSON.stringify(data));
                        refreshTransportHeader();
                    }
                });
        }
        return true;
    } else {
        $row.find('.is-invalid').first().trigger('focus');
        return false;
    }
}

function addExpectedLineRow(table, $button) {
    const $table = $button.closest('table');
    if (Form.process($table)) {
        const row = table.row($button.closest(`tr`));
        const data = row.data();

        row.remove();
        table.row.add(JSON.parse($(`input[name="editableExpectedLineForm"]`).val()));
        table.row.add(data);
        table.draw();

        scrollToBottom();

        // find added row
        const $lastRow = $table.find('tbody tr:last-child');
        const $addedRow = $lastRow.prev();

        // wait for the row to be added
        setTimeout(() => {
            $addedRow.find('select.needed[required]').first().select2('open');
        }, 100);
    }
}

function getShippingRequestStatusHistory() {
    return AJAX.route(GET, `shipping_request_status_history_api`, {shippingRequest: shippingId})
        .json()
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}
