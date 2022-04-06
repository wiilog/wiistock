import '../../../scss/pages/transport/common.scss';

export function initializeFilters(page) {
    initDateTimePicker('#dateMin, #dateMax', 'DD/MM/YYYY', {
        setTodayDate: true
    });

    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(page);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    $(`.filters [name="category"] + label, .filters [name="type"] + label`).on(`click`, function(event) {
        const $label = $(this);
        const $input = $label.prev();
        if($input.is(`:checked`)) {
            event.preventDefault();
            event.stopPropagation();

            $input.prop(`checked`, false);
            if ($input.attr('name') === 'category') {
                $(`.filters [name="type"] + label`).removeClass(`d-none`).addClass(`d-inline-flex`);
            }
        }
    });

    $(`.filters [name="category"]`).on(`change`, function() {
        const category = $(this).val();
        const $filters = $(`.filters`);

        $filters.find(`[name="type"]:not([data-category="${category}"])`).prop(`checked`, false);
        $filters.find(`[name="type"] + label`).addClass(`d-none`).removeClass(`d-inline-flex`);
        $filters.find(`[name="type"][data-category="${category}"] + label`).removeClass(`d-none`).addClass(`d-inline-flex`);
    });
}
