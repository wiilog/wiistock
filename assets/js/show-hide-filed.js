export function showAndRequireInputByType($typeSelect) {
    const $modal = $typeSelect.closest('.modal');
    // find all fixed fields that can be configurable
    const $fields = $modal.find('[data-displayed-type]');

    // remove required symbol from all fields
    $fields.find('.required-symbol')
        .remove();
    $fields.find('.data')
        .removeClass('needed')
        .prop('required', false)

    // find all fields that should be displayed
    const $fieldsToDisplay = $fields.filter(`[data-displayed-type~="${$typeSelect.val()}"]`);

    // find all fields that should be required
    const $fieldsRequired = $fieldsToDisplay.filter(`[data-required-type~="${$typeSelect.val()}"]`);

    // add required symbol to all required fields
    $fieldsRequired.find('.field-label')
        .append($('<span class="required-symbol">*</span>'));
    $fieldsRequired.find('.data')
        .addClass('needed')
        .prop('required', true)

    // find all fields that should not be required and remove required attribute
    const $fieldsNotRequired = $fieldsToDisplay.not($fieldsRequired);
    $fieldsNotRequired.find('.data')
        .removeClass('is-invalid');
    $fieldsNotRequired.find('.invalid-feedback')
        .remove();
    $fieldsNotRequired.find('.invalid-feedback')
        .remove();

    // hide the fields that should not be displayed
    $fields.not($fieldsToDisplay)
        .addClass('d-none');
    // show the fields that should be displayed
    $fieldsToDisplay.removeClass('d-none');
}
