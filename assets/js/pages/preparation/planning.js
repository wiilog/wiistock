import Sortable from "@app/sortable";
import '@styles/pages/preparation/planning.scss';

$(function () {
    $('[data-wii-planning]').on('planning-loaded', function() {
        const $cardColumns = $('.preparation-card-column')

        toggleLoaderState($cardColumns);

        Sortable.create(`.can-drag`, {
            placeholderClass: 'placeholder',
            acceptFrom: false,
        });

        Sortable.create(`.preparation-card-column`, {
            placeholderClass: 'placeholder',
            acceptFrom: '.can-drag',
        });

        $cardColumns.on('sortupdate', function (e) {
            const $destination = $(e.detail.destination.container).find('.card-container');
            const $origin = $(e.detail.origin.container);
            const $currentElement = $(e.detail.item)[0].outerHTML;
            const $element = $(`<div class="can-drag">${$currentElement}</div>`);
            const preparation = $(e.detail.item).data('preparation');
            const date = $destination.parents('.preparation-card-column').data('date');

            $(e.detail.item).remove();
            toggleLoaderState($destination.parents('.preparation-card-column'));
            AJAX.route('PUT', 'preparation_edit_preparation_date', {date, preparation})
                .json()
                .then((_) => {
                    $element.appendTo($destination);

                    Sortable.create(`.can-drag`, {
                        placeholderClass: 'placeholder',
                        acceptFrom: false,
                    });

                    toggleLoaderState($destination.parents('.preparation-card-column'));
                    refreshColumnHint($destination.parents('.preparation-card-column'));
                    refreshColumnHint($origin.parents('.preparation-card-column'));
                    $origin.remove();
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
