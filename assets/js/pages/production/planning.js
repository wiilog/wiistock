import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";
import {openModalUpdateProductionRequestStatus} from '@app/pages/production/form';

const EXTERNAL_PLANNING_REFRESH_RATE = 300000;

global.callbackSaveFilter = callbackSaveFilter;

let planning = null;
let external = null;

$(function () {
    external = Boolean($(`[name=external]`).val());

    planning = new Planning($(`.production-request-planning`), {
        route: `production_request_planning_api`,
        params: {
            external,
        },
        baseDate: moment().startOf(`isoWeek`),
    });

    planning.onPlanningLoad(() => {
        onPlanningLoaded(planning);
    });

    initializeFilters();
    initializePlanningNavigation();
    planning.fetch();

    if(external) {
        initPlanningRefresh();
    }
    const $modalUpdateProductionRequestStatus = $(`#modalUpdateProductionRequestStatus`);

    $(document).on('click', '.open-modal-update-production-request-status', $modalUpdateProductionRequestStatus, (event) => {
        const productionRequest = $(event.target).closest(`a`).data(`id`);
        openModalUpdateProductionRequestStatus($(this), $modalUpdateProductionRequestStatus, productionRequest, () => {
            planning.fetch();
        })
    });
});

function callbackSaveFilter() {
    if (planning) {
        planning.fetch();
    }
}

function refreshColumnHint($column) {
    const productionRequestCount = $column.find(`.preparation-card`).length;
    const productionRequestHint = `${productionRequestCount} demande${productionRequestCount > 1 ? `s` : ``}`;
    $column.find(`.column-hint-container`).html(`<span class='font-weight-bold'>${productionRequestHint}</span>`);
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
            const $column = $destination.closest(`.production-request-card-column`);
            const date = $column.data(`date`);

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
                        refreshColumnHint($column);
                        refreshColumnHint($origin.closest(`.production-request-card-column`));
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
}

function changeNavigationButtonStates() {
    $(`.today-date`)
        .prop(`disabled`, moment().week() === planning.baseDate.week());
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
