import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";
import {displayAttachmentRequired, openModalUpdateProductionRequestStatus} from '@app/pages/production/form'
import {getUserFiltersByPage} from '@app/utils';

const EXTERNAL_PLANNING_REFRESH_RATE = 300000;

global.callbackSaveFilter = callbackSaveFilter;
global.displayAttachmentRequired = displayAttachmentRequired;

let planning = null;
let external = null;

$(function () {
    external = Boolean($(`[name=external]`).val());

    planning = new Planning($(`.production-request-planning`), {
        route: external ? `production_request_planning_api_external`: `production_request_planning_api`,
        params: {
            ... external ? {
                token: $(`[name=token]`).val(),
            } : {},
            sortingType: $(`[name="sortingType"]`).val(),
        },
        baseDate: moment().startOf(`isoWeek`),
    });

    planning.onPlanningLoad(() => {
        onPlanningLoaded(planning);
    });

    initializePlanningNavigation();
    planning.fetch();

    initPlanningRefresh(planning);

    if(!external) {
        initializeFilters();
    }
    const $modalUpdateProductionRequestStatus = $(`#modalUpdateProductionRequestStatus`);

    $(document).on('click', '.open-modal-update-production-request-status', $modalUpdateProductionRequestStatus, (event) => {
        const productionRequest = $(event.target).closest(`a`).data(`id`);
        openModalUpdateProductionRequestStatus($(this), $modalUpdateProductionRequestStatus, productionRequest, () => {
            planning.fetch();
        })
    });
});

function addStyleToTodayCard() {
    const cardSelectorOfTheDay = new Date().toISOString().split(`T`)[0];  // '2024-08-16'
    const cardOfTheDay = $(`.planning-col[data-card-selector="${cardSelectorOfTheDay}"]`);

    if(cardOfTheDay.length > 0) {
        // take first and only children to applu css
        const $card = cardOfTheDay.find(`.wii-box .header`);
        // add style css
        $card.addClass('today-card');
    }
}


function callbackSaveFilter() {
    if (planning) {
        planning.fetch();
    }
}

function initializeFilters() {
    const $filtersContainer = $(`.filters-container`);

    Select2Old.init($filtersContainer.find(`.filter-select2[name=multipleTypes]`), `Types`);
    getUserFiltersByPage(PAGE_PRODUCTION_PLANNING);
    initFilterStatusMutiple();
}

function onPlanningLoaded(planning) {
    const $navigationButtons = $(`.previous-week, .next-week`);
    const $expandedCards = $(`[name=expandedCards]`);

    Sortable.create(`.planning-card-container`, {
        acceptFrom: `.planning-card-container`,
        items: `.can-drag`,
        handle: `.can-drag`,
    });

    Sortable.create($navigationButtons, {
        acceptFrom: `.planning-card-container`,
        items: `.can-drag`,
        handle: `.can-drag`,
    });

    const $cardContainers = planning.$container.find(`.planning-card-container`);

    $navigationButtons.removeClass(`d-none`);

    $navigationButtons.on(`sortenter`, function (e) {
        const $button = $(e.target);

        $button.trigger(`click`);
        $button.find(`.placeholder-container`).remove();
    });

    $navigationButtons.on(`sortleave`, function (e) {
        $navigationButtons.find(`.placeholder-container`).remove();
    })

    $navigationButtons.on(`sortupdate`, function (e) {
        const $button = $(e.target);
        if($button.is(`.previous-week, .next-week`)) {
            $button.find(`.planning-card`).remove();
        }

        $button.find(`.placeholder-container`).remove();
    });

    $cardContainers
        .on(`sortupdate`, function (e) {
            $navigationButtons.find(`.placeholder-container`).remove();

            const $origin = $(e.detail.origin.container);
            const $card = $(e.detail.item);
            const productionRequest = $card.data(`id`);
            const $destination = $card.closest(`.planning-card-container`);
            const $column = $destination.closest(`.planning-col`);
            const date = $column.data(`card-selector`);

            const order = Array.from($(e.target).find(`.planning-card`)).map((card) => $(card).data(`id`));

            const currentProductionRequestIndex = order.indexOf(productionRequest);
            const previousProductionRequest = order[currentProductionRequestIndex - 1] || null;
            const nextProductionRequest = order[currentProductionRequestIndex + 1] || null;

            const stringifiedOrder = JSON.stringify([previousProductionRequest, nextProductionRequest]);

            wrapLoadingOnActionButton($destination, () => (
                AJAX.route(PUT, `production_request_planning_update_expected_at`, {productionRequest, date, order: stringifiedOrder})
                    .json()
                    .then(() => {
                        $(`.tooltip`).tooltip(`hide`);
                        planning.fetch();
                    })
            ));
        });

    $cardContainers.find(`[id^=cardCollapse-]`).on(`show.bs.collapse hide.bs.collapse`, function (e) {
        const productionRequestId = $(this).closest(`.planning-card`).data(`id`);
        let currentExpandedCards = $expandedCards.val()
            ? $expandedCards.val().split(`;`)
            : [];

        const eventType = e.type;
        if(eventType === `show`) {
            currentExpandedCards.push(productionRequestId);
        } else if(eventType === `hide`) {
            const index = currentExpandedCards.indexOf(`${productionRequestId}`);

            if(index > -1) {
                currentExpandedCards.splice(index, 1);
            }
        }

        $expandedCards.val(currentExpandedCards.join(`;`));
    });

    addStyleToTodayCard();

}

function initializePlanningNavigation() {
    $(`.today-date`).on(`click`, function () {
        wrapLoadingOnActionButton($(this), () => (
            planning
                .resetBaseDate(true)
                .then(() => {
                    changeNavigationButtonStates();

                    if(external) {
                        updateRefreshRate();
                    }
                })
        ));
    });

    $(document).on(`click`, `.decrement-date, .previous-week`, function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.previousWeek()
                .then(() => {
                    changeNavigationButtonStates();

                    if(external) {
                        updateRefreshRate();
                    }
                })
        ));
    });

    $(document).on(`click`, `.increment-date, .next-week`, function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.nextWeek()
                .then(() => {
                    changeNavigationButtonStates();

                    if(external) {
                        updateRefreshRate();
                    }
                })
        ));
    });

    $(document).on(`change`, `[name="sortingType"]`, function ($event) {
        planning.params.sortingType = $event.target.value;
        planning.fetch();

        if(external) {
            updateRefreshRate();
        }
    });
}

function changeNavigationButtonStates() {
    $(`.today-date`)
        .prop(`disabled`, moment().week() === planning.baseDate.week());
}

function initPlanningRefresh(planning) {
    eventSource.onmessage = event => {
        const results = JSON.parse(event.data);
        const baseDate = planning.baseDate;
        const baseDatePlus7 = moment(baseDate).add(7, `days`);
        const dates = results.dates.map(date => moment(Date.parse(date)));
        const shouldUpdatePlanning = dates.some(date => date.isAfter(baseDate) && date.isBefore(baseDatePlus7));
        if(shouldUpdatePlanning) {
            planning.fetch().then(() => {
                updateRefreshRate();
            });
        }
    }

    eventSource.onerror(() => {
        setInterval(function () {
            planning.fetch().then(() => {
                updateRefreshRate();
            });
        }, EXTERNAL_PLANNING_REFRESH_RATE);
    })
}

function updateRefreshRate() {
    $(`.refresh-date`).html(`Actualisé le : ${moment().format(`DD/MM/YYYY HH:mm`)}`);
}
