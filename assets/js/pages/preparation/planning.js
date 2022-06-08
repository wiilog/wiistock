import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX from "@app/ajax";
import Planning from "@app/planning";

$(function () {
    const planning = new Planning($('.preparation-planning'), 'preparation_planning_api');
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
});

function refreshColumnHint($column) {
    const preparationCount = $column.find('.preparation-card').length;
    const preparationHint = preparationCount + ' préparation' + (preparationCount > 1 ? 's' : '');
    $column.find('.column-hint-container').html(`<span class='font-weight-bold'>${preparationHint}</span>`);

}
