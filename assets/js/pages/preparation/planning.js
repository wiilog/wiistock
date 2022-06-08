import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX from "@app/ajax";
import Planning from "@app/planning";

global.callbackSaveFilter = callbackSaveFilter;
let planning = null;

$(function () {
    planning = new Planning($('.preparation-planning'), 'preparation_planning_api');
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

    planning.onPlanningLoad(() => {
        Sortable.create('.planning-card-container', {
            placeholderClass: 'placeholder',
            forcePlaceholderSize: true,
            acceptFrom: '.planning-card-container',
            items: '.can-drag',
        });

        const $cardContainers = planning.$container.find('.planning-card-container');

        $cardContainers
            .on('sortupdate', function (e) {
                const $destination = $(this);
                const $origin = $(e.detail.origin.container);
                const preparation = $(e.detail.item).data('preparation');
                const $column = $destination.closest('.preparation-card-column');
                const date = $column.data('date');
                wrapLoadingOnActionButton($destination, () => (
                    AJAX.route('PUT', 'preparation_edit_preparation_date', {date, preparation})
                        .json()
                        .then(() => {
                            refreshColumnHint($column);
                            refreshColumnHint($origin.closest('.preparation-card-column'));
                        })
                ));

            })
    });

    $('.planning-filter').on('click', function() {
        console.log('ey');
        const $checkbox = $(this).find('.filter-checkbox');
        $checkbox.prop('checked', !$checkbox.is(':checked'));
    });
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
