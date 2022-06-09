import '@styles/planning.scss';
import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX, {PUT} from "@app/ajax";
import Planning from "@app/planning";
import moment from "moment";

global.callbackSaveFilter = callbackSaveFilter;
let planning = null;

$(function () {
    planning = new Planning($('.preparation-planning'), {route: 'preparation_planning_api', step: 5});
    planning.onPlanningLoad((event) => {
        onPlanningLoaded(planning);
    });

    initializeFilters();
    initializePlanningNavigation();
});

function callbackSaveFilter() {
    if (planning) {
        planning.fetch();
    }
}

function refreshColumnHint($column) {
    const preparationCount = $column.find('.preparation-card').length;
    const preparationHint = preparationCount + ' préparation' + (preparationCount > 1 ? 's' : '');
    $column.find('.column-hint-container').html(`<span class='font-weight-bold'>${preparationHint}</span>`);
}

function initializeFilters() {
    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_PREPARATION_PLANNING);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
        const planningFilters = data
            .filter((filter) => filter.field.startsWith('planning-status-'));

        if (planningFilters.length > 0) {
            planningFilters.forEach((filter) => $(`input[name=${filter.field}]`).prop('checked', true));
        } else {
            $(`.filter-checkbox`).prop('checked', true)
        }
    }, 'json');

    $('.planning-filter').on('click', function() {
        const $checkbox = $(this).find('.filter-checkbox');
        $checkbox.prop('checked', !$checkbox.is(':checked'));
    });
}

function onPlanningLoaded(planning){
    Sortable.create('.planning-card-container', {
        placeholderClass: 'placeholder-container',
        forcePlaceholderSize: true,
        placeholder: `
            <div class="placeholder-container">
                <div class="placeholder"></div>
            </div>
        `,
        acceptFrom: '.planning-card-container',
        items: '.can-drag',
    });

    const $cardContainers = planning.$container.find('.planning-card-container');

    $cardContainers
        .on('sortupdate', function (e) {
            const $origin = $(e.detail.origin.container);
            const $card = $(e.detail.item);
            const preparation = $card.data('preparation');
            const $destination = $card.closest('.planning-card-container');
            const $column = $destination.closest('.preparation-card-column');
            const date = $column.data('date');
            wrapLoadingOnActionButton($destination, () => (
                AJAX.route(PUT, 'preparation_edit_preparation_date', {date, preparation})
                    .json()
                    .then(() => {
                        refreshColumnHint($column);
                        refreshColumnHint($origin.closest('.preparation-card-column'));
                    })
            ));
        });
}

function initializePlanningNavigation() {
    $('.today-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning
                .resetBaseDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.decrement-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.previousDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.increment-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.nextDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });
}

function changeNavigationButtonStates() {
    const $decrementDate = $('.decrement-date');
    const $todayDate = $('.today-date');

    $todayDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
    $decrementDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
}
