import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";
import {displayAttachmentRequired, openModalUpdateProductionRequestStatus} from '@app/pages/production/form'
import {getUserFiltersByPage} from '@app/utils';

// 5 minutes in milliseconds
const EXTERNAL_PLANNING_REFRESH_RATE = 300000;

global.callbackSaveFilter = callbackSaveFilter;
global.displayAttachmentRequired = displayAttachmentRequired;

let planning = null;
let external = null;
let planningDatesForm = null;

$(function () {
    const $planningDates = $('.planning-dates')
    const $startDate = $planningDates.find(`[name=startDate]`);
    const $endDate = $planningDates.find(`[name=endDate]`);
    planningDatesForm = Form.create($planningDates);

    external = Boolean($(`[name=external]`).val());

    planning = new Planning($(`.production-request-planning`), {
        route: external ? `production_request_planning_api_external`: `production_request_planning_api`,
        params: {
            ... (external
                ? { token: $(`[name=token]`).val(),}
                : {}),
            sortingType: $(`[name="sortingType"]`).val(),
        },
        baseDate: moment().startOf(`isoWeek`),
    });

    planning.onPlanningLoad(() => {
        onPlanningLoaded(planning);
    });

    initializePlanningNavigation($startDate, $endDate);
    selectThisWeek($startDate, $endDate);
    planning.fetch();

    if(external) {
        initPlanningRefresh();
    } else {
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

function updateDateInputs($startDate, $endDate) {
    const datesData = planningDatesForm.process()
    if (!datesData) {
        return
    }

    const startDate = moment($startDate.val());
    let endDate = moment($endDate.val());

    if (startDate.isAfter(endDate)) {
        endDate = startDate;
        $endDate.val(endDate.format(`YYYY-MM-DD`));
    }

    planning.baseDate = startDate;
    planning.step = endDate.diff(planning.baseDate, `days`)+1;

    return planning.fetch().then(() => {
        changeNavigationButtonStates();
        if(external) {
            updateRefreshRate();
        }
    });
}

function selectThisWeek($startDate, $endDate) {
    const now = moment().startOf(`isoWeek`).format(`YYYY-MM-DD`)
    $startDate.val(now);
    planning.step = 6;
    $endDate.val(moment($startDate.val()).add(planning.step, `days`).format(`YYYY-MM-DD`));
}

function initializePlanningNavigation($startDate, $endDate) {
    $startDate.add($endDate).on(`change`, function () {
        updateDateInputs($startDate, $endDate)
    })

    $(`.today-date`).on(`click`, function () {
        wrapLoadingOnActionButton($(this), () => {
            selectThisWeek($startDate, $endDate)
            return updateDateInputs($startDate, $endDate)
        });
    });

    $(document).on(`click`, `.decrement-date, .previous-week`, function () {
        wrapLoadingOnActionButton($(this), () => {
            $startDate.val(moment($startDate.val()).add(0-planning.step, `days`).format(`YYYY-MM-DD`));
            $endDate.val(moment($startDate.val()).add(planning.step-1, `days`).format(`YYYY-MM-DD`));
            return updateDateInputs($startDate, $endDate)
        });
    });

    $(document).on(`click`, `.increment-date, .next-week`, function () {
        wrapLoadingOnActionButton($(this), () => {
            $startDate.val(moment($startDate.val()).add(planning.step, `days`).format(`YYYY-MM-DD`));
            $endDate.val(moment($startDate.val()).add(planning.step-1, `days`).format(`YYYY-MM-DD`));
            return updateDateInputs($startDate, $endDate)
        });
    });

    $(document).on(`change`, `[name="sortingType"]`, function ($event) {
        wrapLoadingOnActionButton($(this), () => {
            planning.params.sortingType = $event.target.value;
            return updateDateInputs($startDate, $endDate)
        });
    });
}

function changeNavigationButtonStates() {
    $(`.today-date`)
        .prop(`disabled`, moment().week() === planning.baseDate.week() && planning.step === 7);
}

function initPlanningRefresh() {
    setInterval(function () {
        planning.fetch().then(() => {
            updateRefreshRate();
        });
    }, EXTERNAL_PLANNING_REFRESH_RATE);
}

function updateRefreshRate() {
    $(`.refresh-date`).html(`Actualisé le : ${moment().format(`DD/MM/YYYY HH:mm`)}`);
}
