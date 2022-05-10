import '@styles/pages/transport/common.scss';
import '@styles/pages/transport/round-plan.scss'
import {Map} from '@app/map';
import Sortable from "../../../sortable";

$(function () {
    const map = Map.create(`map`);

    const transportOrders = Array($(`input[name=transportOrders]`).val());
    console.log(transportOrders);

    const sortable = Sortable.create(`.card-container`, {
        acceptFrom: `.card-container`,
        placeholderClass: 'placeholder',
    });

    updateCardsContainers();

    $(sortable).on('sortupdate', () => {
        updateCardsContainers();
    })

    $('.btn-cross').on('click', (event) => {
        removeCard(event.currentTarget);
    });

});

function updateCardsContainers() {
    $('#to-affect-container').children().each((index, card) => {
        card.firstElementChild.style.display = 'none';
        card.querySelector('.btn-cross').style.display = 'none';
    });

    $('#affected-container').children().each((index, card) => {
        card.firstElementChild.style.display = 'flex';
        card.firstElementChild.innerHTML = index + 1;
        card.querySelector('.btn-cross').style.display = 'flex';
    });
}

function removeCard(btn) {
    let card = btn.parentNode.parentNode.parentNode.parentNode
    card.parentNode.removeChild(card);
    $('#to-affect-container').append(card);
    updateCardsContainers();
}
