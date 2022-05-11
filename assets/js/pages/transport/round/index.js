import '@styles/pages/transport/common.scss';
import {initializeFilters} from "@app/pages/transport/common";

$(function() {
    initializeFilters(PAGE_TRANSPORT_ROUNDS);

    let table = initDataTable('tableRounds', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ajax: {
            url: Routing.generate(`transport_round_api`),
            type: "POST",
            data: data => {
                data.dateMin = $(`.filters [name="dateMin"]`).val();
                data.dateMax = $(`.filters [name="dateMax"]`).val();
            }
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
});

