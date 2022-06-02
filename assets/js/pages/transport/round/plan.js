import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/round-plan.scss'
import {Map} from '@app/map';
import Sortable from "../../../sortable";
import AJAX, {GET, POST} from "@app/ajax";
import Form from "@app/form";
import Flash, {ERROR} from "@app/flash";

const roundMarkerAlreadySaved = {};

$(function () {
    const map = Map.create(`map`);

    const contactData = JSON.parse($(`input[name=contactData]`).val());

    const $startPoint = $('.round-form-container [name=startPoint]');
    const $startPointScheduleCalculation = $('.round-form-container [name=startPointScheduleCalculation]');
    const $endPoint = $('.round-form-container [name=endPoint]');

    const isOnGoing = Boolean(Number($(`input[name=isOnGoing]`).val()));

    updateCardsContainers(map, contactData);
    initializeRoundPointMarkers(map);
    initializeForm(map);

    Promise
        .all([
            placeAddressMarker($startPoint, map),
            placeAddressMarker($startPointScheduleCalculation, map),
            placeAddressMarker($endPoint, map)
        ])
        .then(() => {
            map.fitBounds();
        })

    initialiseMouseHoverEvent(map, contactData);

    Sortable.create(`#to-affect-container`, {
        placeholderClass: 'placeholder',
    });

    Sortable.create(`#affected-container`, {
        placeholderClass: 'placeholder',
        acceptFrom: '#to-affect-container',
        items: '.to-assign',
    });

    Sortable.create(`#affected-container`, {
        placeholderClass: 'placeholder',
        acceptFrom: '.sortable-container',
    });

    $('.sortable-container').on('sortupdate', function (){
        updateCardsContainers(map, contactData);
    })

    $('.btn-cross').on('click', function() {
        removeCard($(this), map, contactData);
    });

    $.merge(
        $startPoint,
        $.merge(
            $startPointScheduleCalculation,
            $endPoint
        )
    ).on('change', function () {
        placeAddressMarker($(this), map);
    });

    $('.deliverer-picker').on('change',function (){
        const $btnCalculateTime = $('.btn-calculate-time');
        const $inputTime = $('input[name="expectedAtTime"]');
        const $delivererPicker = $(this);
        if($delivererPicker.val()){
            $btnCalculateTime.removeClass('btn-disabled');
            $inputTime.prop('disabled', false);
            const [deliverer] = $delivererPicker.select2('data');
            if(deliverer){
                $inputTime.val(deliverer.startingHour);
            }
        }else{
            $btnCalculateTime.addClass('btn-disabled');
            $inputTime
                .prop('disabled', true)
                .val(null);
        }
    })

    $('#delivered-container').children().each((index, card) => {
        const $card = $(card);
        $card.find('.affected-number')
            .removeClass('d-none')
            .text(index + 1);
        $card.find('.btn-cross')
            .removeClass('d-none')
            .on('click', function() {
                removeCard($(this), map, contactData);
            });
        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: "blueLocation",
            popUp: map.createPopupContent(contact, index + 1,{
                color: "#666"
            }),
        });
    });

    $('.btn-calculate-time').on('click', function () {
        wrapLoadingOnActionButton($(this), () => {
            if (!$(this).hasClass('btn-disabled')) {
                const $expectedAtTime = $('input[name="expectedAtTime"]');
                const $startPoint = $('input[name="startPoint"]');
                const $startPointScheduleCalculation = $('input[name="startPointScheduleCalculation"]');
                const $endPoint = $('input[name="endPoint"]');

                if ($expectedAtTime.val()
                    && $startPoint.val()
                    && $startPointScheduleCalculation.val()
                    && $endPoint.val()
                ) {
                    const expectedTime = $expectedAtTime.val();
                    let roundHours = Number(expectedTime.substring(0, 2));
                    let roundMinutes = Number(expectedTime.substring(3, 5));
                    let expectedTimeInMinutes = roundHours * 60 + roundMinutes;
                    let distance = 0;
                    let time = 0;

                    const params = extractParametersFromDOM($expectedAtTime, $startPoint, $startPointScheduleCalculation, $endPoint);
                    return $.get(Routing.generate('transport_round_calculate'), params, function(response) {
                        const roundData = Object.keys(response.roundData).sort().reduce((obj, key) => {
                            obj[key] = response.roundData[key];
                            return obj;
                        }, {});

                        Object.values(roundData.data).forEach((round, index) => {
                            [distance, time] = parseRouteIntels(round, distance, time, expectedTimeInMinutes, index, map, params, roundData);
                        });

                        saveAndDisplayEstimatedTimesInDOM(distance, time);
                        map.setLines(response.coordinates.map((coordinate) => [coordinate['latitude'], coordinate['longitude']]), "#3353D7");
                    });
                } else {
                    Flash.add(ERROR, 'Calcul impossible. Veillez bien à renseigner les points de départs, d\'arrivée, ainsi que l\'heure de départ');
                    return new Promise((resolve) => resolve());
                }
            }
        })
    })
});

function saveAndDisplayEstimatedTimesInDOM(distance, time) {
    const estimatedTotalTime = minutesToTime(time);
    const strDistance = Number(distance).toFixed(2);

    $('.estimatedTotalDistance').text(`${strDistance}km`);
    $('.estimatedTotalTime').text(estimatedTotalTime);
    $('input[name="estimatedTotalDistance"]').val(strDistance);
    $('input[name="estimatedTotalTime"]').val(estimatedTotalTime);
}

function parseRouteIntels(round, distance, time, expectedTimeInMinutes, index, map, params, roundData) {
    const waitingTimes = JSON.parse($('input[name="waitingTime"]').val());

    distance += round.distance;
    let roundHours = Number(round.time.substring(0, 2));
    let roundMinutes = Number(round.time.substring(3, 5));
    let roundTime = roundHours * 60 + roundMinutes;
    const waitingTime = round.destinationType ? Number(waitingTimes[round.destinationType]) : 0;
    roundTime += waitingTime;

    if (index > 0) {
        time += roundTime;
    }

    const elapsed = expectedTimeInMinutes + time;

    const arrivedTime = minutesToTime(elapsed);

    const isLastIteration = index === Object.values(roundData.data).length - 1;

    // name of address input or order id
    const selector = (
        index === 0 ? 'startPointScheduleCalculation' :
        isLastIteration ? 'endPoint' :
        params.orders.find((order) => order.index === index - 1).order
    );

    $('#affected-container')
        .find(`.order-card.assigned[data-order-id=${selector}]`)
        .data('order-time', arrivedTime)
        .attr('data-order-time', arrivedTime);

    map.estimatePopupMarker({
        selector,
        estimation: arrivedTime,
    });

    return [distance, time];
}

function extractParametersFromDOM($expectedAtTime, $startPoint, $startPointScheduleCalculation, $endPoint) {
    const expectedTime = $expectedAtTime.val();

    const params = {
        startingTime: expectedTime,
        startingPoint: $startPoint.val(),
        timeStartingPoint: $startPointScheduleCalculation.val(),
        endingPoint: $endPoint.val(),
        orders: []
    };

    $('#affected-container, #delivered-container').children().each((index, card) => {
        const $card = $(card);
        let order = $card.data('order-id');
        params.orders.push({
            index,
            order
        })
    });

    return params;
}

function initialiseMouseHoverEvent(map, contactData) {

    $('.cards-mouse-hover .order-card')
        .off('mouseenter.orderCardHover')
        .off('mouseleave.orderCardHover')

        .on('mouseenter.orderCardHover', function(){
            const $card = $(this);
            const color = ($card.parent().attr('id') == 'delivered-container') ? "#666" : "#3353d7";
            const currentIndex = $card.find('.affected-number:not(.d-none)').text();
            $card.addClass('focus-border');
            let contact = contactData[$card.data('order-id')];
            map.setMarker({
                latitude: contact.latitude,
                longitude: contact.longitude,
                icon: currentIndex ? "blueLocation" : "greyLocation",
                popUp: map.createPopupContent(contact, currentIndex, color),
                isFocused: true,
                selector: $card.data('order-id')
            });
        })
        .on('mouseleave.orderCardHover',function (){
            const $card = $(this);
            $card.removeClass('focus-border');
            const currentIndex = $card.find('.affected-number:not(.d-none)').text();
            const color = ($card.parent().attr('id') =='delivered-container') ? "#666" : "#3353d7";
            const contact = contactData[$card.data('order-id')];

            map.setMarker({
                latitude: contact.latitude,
                longitude: contact.longitude,
                icon: currentIndex ? "blueLocation" : "greyLocation",
                popUp: map.createPopupContent(contact, currentIndex, {
                    color
                }),
                onclick: () => {
                    if (!currentIndex) {
                        affectCard($card, map, contactData);
                    }
                },
                selector: $card.data('order-id')
            });
    });
}

function updateCardsContainers(map, contactData, deletion = false) {
    initialiseMouseHoverEvent(map, contactData);

    $('#to-affect-container').children().each((index, card) => {
        const $card = $(card);
        $card.find('.affected-number').addClass('d-none');
        $card.find('.btn-cross').addClass('d-none');

        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: "greyLocation",
            popUp: map.createPopupContent(contact, null),
            onclick: function () {
                affectCard($card, map, contactData);
            },
            selector: $card.data('order-id'),
            deletion
        });
    });

    let offset = $('#delivered-container').children().length;
    $('#affected-container').children().each((index, card) => {
        const $card = $(card);
        const cardPriority = index + 1 + offset;
        $card.find('.affected-number')
            .removeClass('d-none')
            .text(cardPriority);
        $card.find('.btn-cross')
            .removeClass('d-none')
            .on('click', function() {
                removeCard($(this), map, contactData);
            });
        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: "blueLocation",
            popUp: map.createPopupContent(contact, cardPriority, {
                time: $card.data('order-time'),
                timeLabel: $card.data('order-time-label'),
            }),
            selector: $card.data('order-id'),
        });
    });
}

function removeCard($button, map, contactData) {
    const $card = $button.closest('.order-card');
    $card.remove();
    $('#to-affect-container').append($card);
    updateCardsContainers(map, contactData, true);
}

function placeAddressMarker($input, map){
    const name = $input.attr('name');
    const address = $input.val();
    const $form = $input.closest('.round-form-container');

    if (address) {
        return AJAX.route(GET, 'transport_round_address_coordinates_get', {address})
            .json()
            .then(function (response) {
                if (response.success) {
                    if (roundMarkerAlreadySaved[name]) {
                        map.removeMarker(roundMarkerAlreadySaved[name]);
                    }
                    const latitude = response.latitude;
                    const longitude = response.longitude;
                    const marker = addRoundPointMarker(map, $input, {latitude, longitude});
                    saveCoordinatesByAddress($form, name, response, marker);
                }
                else {
                    saveCoordinatesByAddress($form, name, null);
                    Flash.add(ERROR, "Une erreur s'est produite lors de la récupération de la position de l'adresse GPS")
                }
            });
    }
    else {
        saveCoordinatesByAddress($form, name, null);
        return Promise.resolve();
    }
}

function initializeForm(map) {
    Form.create($('.round-form-container'))
        .addProcessor((data, errors) => {
            const $affectedOrders = $('#affected-container .order-card');
            if (!$affectedOrders.exists()) {
                errors.push({message: 'Vous devez ajouter au moins un ordre dans la tournée pour continuer'});
            }
            else {
                const ordersAndTimes = $affectedOrders
                    .map((_, orderCard) => {
                        const $card = $(orderCard);
                        return {
                            id: $card.data('order-id'),
                            time: $card.data('order-time')
                        }
                    })
                    .toArray();
                data.append('affectedOrders', JSON.stringify(ordersAndTimes));
            }
        })
        .onSubmit((data) => {
            wrapLoadingOnActionButton($('.round-form-container').find('button[type="submit"]'), () => {
                return AJAX.route(POST, 'transport_round_save')
                    .json(data)
                    .then(({success, msg, data, redirect}) => {
                        if (!success) {
                            if (data.newNumber) {
                                resetRoundNumber(data.newNumber);
                            }
                            Flash.add(ERROR, msg, true, true);
                        } else {
                            location.href = redirect;
                        }
                    })
            })
        })

}

function resetRoundNumber(number) {
    $('.round-number').each(function() {
        const $elem = $(this);
        if ($elem.is('input')) {
            $elem.val(number);
        }
        else {
            $elem.text(number);
        }
    });
}

function addRoundPointMarker(map, $input, {latitude, longitude}) {
    if ($input.attr('name') !== 'endPoint'
        || map.locations.every(({latitude: saved_latitude, longitude: saved_longitude}) => (saved_latitude !== latitude || saved_longitude !== longitude))) {
        return map.setMarker({
            latitude,
            longitude,
            icon: "blackLocation",
            popUp: map.createPopupContent({contact: $input.data('short-label')}),
            selector: $input.attr('name'),
        });
    }
    return undefined;
}

function initializeRoundPointMarkers(map) {
    const $form = $('.round-form-container');
    const $coordinates = $form.find('[name=coordinates]')
    const coordinates = JSON.parse($coordinates.val());
    for(const name of Object.keys(coordinates)) {
        const pointCoordinates = coordinates[name];
        const $input = $form.find(`[name=${name}]`);
        addRoundPointMarker(map, $input, pointCoordinates);
    }
}

function affectCard($card, map, contactData) {
    $card.remove();
    $('#affected-container').append($card);
    updateCardsContainers(map ,contactData);
}

function saveCoordinatesByAddress($form, name, pointCoordinates, marker = null) {

    const $coordinates = $form.find('[name=coordinates]');
    let coordinates = JSON.parse($coordinates.val()) || {};
    coordinates = Array.isArray(coordinates) ? {} : coordinates;

    if (name && pointCoordinates) {
        coordinates[name] = {
            latitude: pointCoordinates.latitude,
            longitude: pointCoordinates.longitude
        };

        if (marker) {
            roundMarkerAlreadySaved[name] = marker;
        }
    }
    else {
        delete roundMarkerAlreadySaved[name];
        delete coordinates[name];
    }

    $coordinates.val(JSON.stringify(coordinates));
}

function minutesToTime(timestamp) {
    const hours = Math.floor(timestamp / 60);
    const strHours = (hours < 10 ? '0' : '') + hours;
    const minutes = timestamp % 60;
    const strMinutes = (minutes < 10 ? '0' : '') + minutes;

    return `${strHours}:${strMinutes}`;
}
