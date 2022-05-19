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

    updateCardsContainers(map, contactData);
    initializeRoundPointMarkers(map);
    initializeForm();

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

    const sortable = Sortable.create(`.card-container`, {
        placeholderClass: 'placeholder',
    });

    Sortable.create(`#affected-container`, {
        acceptFrom: '#to-affect-container',
        items: '.to-assign'
    });

    Sortable.create(`#affected-container`, {
        acceptFrom: '.card-container',
    });


    $(sortable).on('sortupdate', function (){
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
});

function initialiseMouseHoverEvent(map, contactData) {

    $('.card-container .order-card')
        .off('mouseenter.orderCardHover')
        .off('mouseleave.orderCardHover')

        .on('mouseenter.orderCardHover', function(){
            const $card = $(this);
            const currentIndex = $card.find('.affected-number:not(.d-none)').text();
            $card.addClass('focus-border');
            let contact = contactData[$card.data('order-id')];
            map.setMarker({
                latitude: contact.latitude,
                longitude: contact.longitude,
                icon: currentIndex ? "blueLocation" : "greyLocation",
                popUp: createPopupContent(contact, currentIndex),
                isFocused: true,
            });
        })
        .on('mouseleave.orderCardHover',function (){
            const $card = $(this);
            $card.removeClass('focus-border');
            const currentIndex = $card.find('.affected-number:not(.d-none)').text();

        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: currentIndex ? "blueLocation" : "greyLocation",
            popUp: createPopupContent(contact, currentIndex),
            onclick: () => {
                if (!currentIndex) {
                    affectCard($card, map, contactData);
                }
            }
        });
    });
}

function updateCardsContainers(map, contactData) {
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
            popUp: createPopupContent(contact, null),
            onclick: function () {
                affectCard($card, map, contactData);
            }
        });
    });

    $('#affected-container').children().each((index, card) => {
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
            popUp: createPopupContent(contact, index + 1),
        });
    });
}

function removeCard($button, map, contactData) {
    const $card = $button.closest('.order-card');
    $card.remove();
    $('#to-affect-container').append($card);
    updateCardsContainers(map ,contactData);
}

function createPopupContent(contactInformation, index) {
    const htmlIndex = index ? `<span class='index'>${index}</span>` : ``;
    const htmlTime = contactInformation.time ? `<span class='time'>${contactInformation.time || ""}</span>` : ``;
    return `
        ${htmlIndex}
        <span class='contact'>${contactInformation.contact || ""}</span>
        ${htmlTime}
    `;
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

function initializeForm() {
    Form.create($('.round-form-container'))
        .addProcessor((data, errors) => {
            const $affectedOrders = $('#affected-container .order-card');
            if (!$affectedOrders.exists()) {
                errors.push({message: 'Vous devez ajouter au moins un ordre dans la tournée pour continuer'});
            }
            else {
                const orderIds = $affectedOrders
                    .map((_, orderCard) => $(orderCard).data('order-id'))
                    .toArray()
                    .join(',')
                data.append('affectedOrders', orderIds);
            }
        })
        .onSubmit((data) => {
            /// TODO Add loader ? on submit button
            AJAX.route(POST, 'transport_round_save')
                .json(data)
                .then(({success, msg, data, redirect}) => {
                    if (!success) {
                        if (data.newNumber) {
                            resetRoundNumber(data.newNumber);
                        }
                        Flash.add(ERROR, msg, true, true);
                    }
                    else {
                        location.href = redirect;
                    }
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
            popUp: createPopupContent({contact: $input.data('short-label')}),
            name: $input.data('short-label'),
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
