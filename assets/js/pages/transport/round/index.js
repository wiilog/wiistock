import '@styles/pages/transport/common.scss';
import {initializeFilters, placeDeliverer} from "@app/pages/transport/common";
import {Map} from "@app/map";

$(function() {
    initializeFilters(PAGE_TRANSPORT_ROUNDS);

    let table = initDataTable('tableRounds', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        pageLength: 6,
        lengthMenu: [6],
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

    const deliverersPositions= JSON.parse(($(`input[name=deliverersPositions]`).val()));

    const map = Map.create(`map`);
    deliverersPositions.forEach((position, index) => {
        placeDeliverer(map, position, index );
    });
    map.fitBounds()

});
