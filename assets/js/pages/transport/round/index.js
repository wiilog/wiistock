import AJAX, {GET, POST} from "@app/ajax";
import Flash from "@app/flash";
import {initializeForm, cancelRequest, deleteRequest} from "@app/pages/transport/request/common";
import {initializeFilters} from "@app/pages/transport/common";

$(function() {
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
