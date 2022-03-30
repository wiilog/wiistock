export function onRequestTypeChange($requestType) {
    const requestType = $requestType.val();
    const $form = $requestType.closest('.modal');
    const $specificsItems = $form.find(`[data-request-type]`);
    $specificsItems.addClass('d-none');
    const $types = $form
        .find('[name=type]')
        .prop('checked', false)
        .prop('disabled', true);

    $form.find('[data-type]').addClass('d-none');

    if (requestType) {
        const $specificItemsToDisplay = $specificsItems.filter(`[data-request-type=""], [data-request-type="${requestType}"]`);
        $specificItemsToDisplay.removeClass('d-none');

        const $labels = $specificItemsToDisplay.filter(`label`);
        $labels.each(function() {
            const $label = $(this);
            const id = $label.attr('for');
            const $input = $types.filter(`#${id}`);
            $input.prop('disabled', false);
            $label.removeClass('d-none');
        });
    }
    else {
        $specificsItems.filter(`[data-request-type=""]`).addClass('d-none');
    }
}

export function onTypeChange($type) {
    const type = $type.val();
    const $form = $type.closest('.modal');
    $form
        .find('[data-type]')
        .addClass('d-none');

    $form
        .find('[data-type]')
        .find('[type=checkbox]')
        .prop('checked', false)

    $form.find(`[data-type]`).each(function() {
        const $element = $(this);
        const allowedTypes = $element.data('type');
        if (allowedTypes.some((t) => (t == type))) {
            $element.removeClass('d-none');
        }
    });
}
