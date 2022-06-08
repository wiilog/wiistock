import '@styles/pages/preparation/planning.scss';
import Sortable from "@app/sortable";
import AJAX from "@app/ajax";
import Planning from "@app/planning";

$(function () {
    const planning = new Planning();
    $('[data-wii-planning]').on('planning-loaded', function() {
        const $cardColumns = $('.preparation-card-column')

        toggleLoaderState($cardColumns);

        Sortable.create('.card-container', {
            placeholderClass: 'placeholder',
            acceptFrom: '.card-container',
            items: '.can-drag'
        });

        $('.card-container').on('sortupdate', function (e) {
            const $destination = $(this);
            const $origin = $(e.detail.origin.container);
            const preparation = $(e.detail.item).data('preparation');
            const $column = $destination.closest('.preparation-card-column');
            const date = $column.data('date');
            toggleLoaderState($column);
            AJAX.route('PUT', 'preparation_edit_preparation_date', {date, preparation})
                .json()
                .then(() => {
                    toggleLoaderState($column);
                    refreshColumnHint($column);
                    refreshColumnHint($origin.closest('.preparation-card-column'));
                });
        })
    });
});


function toggleLoaderState($elements) {
    const $loaderContainers = $elements.find('.loader-container');
    const $cardContainers = $elements.find('.card-container');
    $loaderContainers.each(function() {
        $(this).toggleClass('d-none');
    });
    $cardContainers.each(function() {
        $(this).toggleClass('d-none');
    });
}

function refreshColumnHint($column) {
    const preparationCount = $column.find('.preparation-card').length;
    const preparationHint = preparationCount + ' préparation' + (preparationCount > 1 ? 's' : '');
    $column.find('.column-hint-container').html(`<span class='font-weight-bold'>${preparationHint}</span>`);

}
