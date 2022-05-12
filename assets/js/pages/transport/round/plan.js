import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/round-plan.scss'
import {Map} from '@app/map';
import Sortable from "../../../sortable";
import AJAX, {GET, POST} from "@app/ajax";
import Form from "@app/form";
import Flash, {ERROR} from "@app/flash";

$(function () {
    const map = Map.create(`map`);

    const contactData = JSON.parse($(`input[name=contactData]`).val());

    updateCardsContainers(map, contactData);
    initializeForm();
    map.fitBounds();

    const sortable = Sortable.create(`.card-container`, {
        acceptFrom: `.card-container`,
        placeholderClass: 'placeholder',
    });

    $(sortable).on('sortupdate', function (){
        updateCardsContainers(map, contactData);
    })

    $('.btn-cross').on('click', (event) => {
        removeCard(event.currentTarget , map);
    });

    $('.card-container .order-card').on('mouseenter', function(){
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
    });

    $('.card-container .order-card').on('mouseleave',function (){
        const $card = $(this);
        $card.removeClass('focus-border');
        const currentIndex = $card.find('.affected-number:not(.d-none)').text();

        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: currentIndex ? "blueLocation" : "greyLocation",
            popUp: createPopupContent(contact, currentIndex),
        });
    });

    $.merge(
        $('.round-form-container [name=startPoint]'),
        $.merge(
            $('.round-form-container [name=startPointScheduleCalculation]'),
            $('.round-form-container [name=endPoint]')
        )
    ).on('change', function () {
        // TODO supprimer le marker deja présent pour le point
        placeAddressMarker($(this), map);
    });

});

function updateCardsContainers(map, contactData) {
    $('#to-affect-container').children().each((index, card) => {
        const $card = $(card);
        $card.find('.affected-number').addClass('d-none');
        $card.find('.btn-cross').addClass('d-none');

        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: "greyLocation",
            popUp: createPopupContent(contact),
        });

    });

    $('#affected-container').children().each((index, card) => {
        const $card = $(card);
        $card.find('.affected-number')
            .removeClass('d-none')
            .text(index + 1);
        $card.find('.btn-cross').removeClass('d-none');

        let contact = contactData[$card.data('order-id')];
        map.setMarker({
            latitude: contact.latitude,
            longitude: contact.longitude,
            icon: "blueLocation",
            popUp: createPopupContent(contact, index + 1)
        });
    });
}

function removeCard(btn , map) {
    let card = btn.parentNode.parentNode.parentNode.parentNode
    card.parentNode.removeChild(card);
    $('#to-affect-container').append(card);
    updateCardsContainers(map);
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
    const address = $input.val();

    AJAX.route(GET,'transport_round_address_coordinates_get', {address})
        .json()
        .then(function (response) {
            if (response.success) {
                if ($input.attr('name') !== 'endPoint' || map.locations.every(({latitude, longitude}) => (latitude !== response.latitude || longitude !== response.longitude))) {
                    map.setMarker({
                        latitude: response.latitude,
                        longitude: response.longitude,
                        icon: "blackLocation",
                        popUp: createPopupContent({contact: $input.data('short-label')}),
                    });
                }
            }
            else {
                /// TODO show error
            }
        });
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
