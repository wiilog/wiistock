import {Map} from "@app/map";

export function initializeFilters(page) {
    initDateTimePicker('#dateMin', 'DD/MM/YYYY', {
        setTodayDate: page !== PAGE_TRANSPORT_ROUNDS
    });

    initDateTimePicker('#dateMax', 'DD/MM/YYYY', {
        setTodayDate: false
    });

    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(page);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    $(`.filters [name="category"] + label, .filters [name="type"] + label`).on(`click`, function(event) {
        const $label = $(this);
        const $input = $label.prev();
        if($input.is(`:checked`)) {
            event.preventDefault();
            event.stopPropagation();

            $input.prop(`checked`, false);
            if ($input.attr('name') === 'category') {
                $(`.filters [name="type"] + label`).removeClass(`d-none`).addClass(`d-inline-flex`);
            }
        }
    });

    $(`.filters [name="category"]`).on(`change`, function() {
        const category = $(this).val();
        const $filters = $(`.filters`);

        $filters.find(`[name="type"]:not([data-category="${category}"])`).prop(`checked`, false);
        $filters.find(`[name="type"] + label`).addClass(`d-none`).removeClass(`d-inline-flex`);
        $filters.find(`[name="type"][data-category="${category}"] + label`).removeClass(`d-none`).addClass(`d-inline-flex`);
    });
}

export function getStatusHistory(transportId, transportType) {
    $.get(Routing.generate(`status_history_api`, {id:transportId,type: transportType}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.history-container`);
            $statusHistoryContainer.html(template);
        });
}

export function getTransportHistory(transportId, transportType) {
    $.get(Routing.generate(`transport_history_api`, {id: transportId, type: transportType}, true))
        .then(({template}) => {
            const $transportHistoryContainer = $(`.transport-history-container`);
            $transportHistoryContainer.html(template);
        });
}

export function getPacks(transportId, transportType) {
    $.get(Routing.generate(`transport_packs_api`, {id: transportId, type: transportType}, true))
        .then(({template, packingLabel}) => {
            const $packsContainer = $(`.packs-container`);
            $packsContainer.html(template);
            const $packingLabelCounter = $('.packing-label-counter');
            $packingLabelCounter.text(packingLabel);
        });
}

export function getTransportRoundTimeline(transportRoundId){
    $.get(Routing.generate(`round_transport_history_api`, {round: transportRoundId}, true))
        .then(({template}) => {
            const $transportListContainer = $(`.transport-list-container`);
            $transportListContainer.html(template);
        });
}

export function initMap(contactPosition) {
    const map = Map.create(`map`);
    map.setMarker({
        latitude : contactPosition[0],
        longitude : contactPosition[1],
        icon : "blueLocation",
        popUp: "",
        name: "contact",
    });
    map .fitBounds();
    return map;
}

export function placeDeliverer(map , delivererPosition , name = null) {
    if (delivererPosition) {
        let position = delivererPosition.split(',');
        map.setMarker({
            latitude : position[0],
            longitude : position[1],
            icon : "delivererLocation",
            popUp: "",
            name: "Deliverer",
        });
    }
}
