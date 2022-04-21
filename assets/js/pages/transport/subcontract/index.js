import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/subcontract.scss';
import {$document} from "@app/app";
import {GET, POST} from "@app/ajax";

$(function () {

    let table = initDataTable('tableSubcontractOrders', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        pageLength: 24,
        lengthMenu: [24, 48, 72, 96],
        ajax: {
            url: Routing.generate(`transport_subcontract_api`),
            type: "POST",
            data: data => {
                data.dateMin = $(`.filters [name="dateMin"]`).val();
                data.dateMax = $(`.filters [name="dateMax"]`).val();
            },
        },
        domConfig: {
            removeInfo: true
        },
        //remove empty div with mb-2 that leaves a blank space
        drawCallback: () => $(`.row.mb-2 .col-auto.d-none`).parent().remove(),
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {data: 'content', name: 'content', orderable: false},
        ],
    });

    $document.arrive('.accept-request, .subcontract-request', function () {
        $(this).on('click', function () {
            const requestId = $(this).siblings('[name=requestId]').val();
            const buttonType = $(this).data('type') ;
            wrapLoadingOnActionButton($(this), () =>
                AJAX.route(POST, 'transport_request_treat', {requestId, buttonType}).json().then(() => table.ajax.reload())
            )
        });
    });
});
