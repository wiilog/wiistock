import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";

global.callbackSaveFilter = callbackSaveFilter;
let planning = null;

$(function () {
    planning = new Planning($('.production-request-planning'), {route: 'production_request_planning_api', baseDate: moment().startOf(`isoWeek`)});
    planning.onPlanningLoad(() => {
        onPlanningLoaded(planning);
    });

    initializeFilters();
    initializePlanningNavigation();
});

function callbackSaveFilter() {
    if (planning) {
        console.log("test");
        planning.fetch();
    }
}

function refreshColumnHint($column) {
    const productionRequestCount = $column.find('.preparation-card').length;
    const productionRequestHint = `${productionRequestCount} demande${productionRequestCount > 1 ? `s` : ``}`;
    $column.find('.column-hint-container').html(`<span class='font-weight-bold'>${productionRequestHint}</span>`);
}

function initializeFilters() {
    const $filtersContainer = $(`.filters-container`);

    Select2Old.init($filtersContainer.find(`.filter-select2[name=multipleTypes]`), `Types`);
    getUserFiltersByPage(PAGE_PRODUCTION_PLANNING);
}

function onPlanningLoaded(planning) {
    Sortable.create('.planning-card-container', {
        acceptFrom: '.planning-card-container',
        items: '.can-drag',
        handle: '.can-drag',
    });

    const $cardContainers = planning.$container.find('.planning-card-container');

    $cardContainers
        .on('sortupdate', function (e) {
            const $origin = $(e.detail.origin.container);
            const $card = $(e.detail.item);
            const productionRequest = $card.data('production-request-id');
            const $destination = $card.closest('.planning-card-container');
            const $column = $destination.closest('.production-request-card-column');
            const date = $column.data('date');
            wrapLoadingOnActionButton($destination, () => (
                AJAX.route(PUT, 'preparation_edit_preparation_date', {date, productionRequest})
                    .json()
                    .then(() => {
                        refreshColumnHint($column);
                        refreshColumnHint($origin.closest('.production-request-card-column'));
                    })
            ));
        });
}

function initializePlanningNavigation() {
    $('.today-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning
                .resetBaseDate(true)
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.decrement-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.previousWeek()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.increment-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.nextWeek()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });
}

function changeNavigationButtonStates() {
    const $todayDate = $('.today-date');
    $todayDate.prop('disabled', moment().week() === planning.baseDate.week());
}


