function initNewDispatchEditor(modal) {
    clearModal(modal);
    const $modal = $(modal);
    onDispatchTypeChange($modal.find("[name=type]"));

    initDatePickers();
}

function onDispatchTypeChange($select) {
    const $modal = $select.closest('.modal');
    onTypeChange($select);
    const $selectedOption = $select.find('option:selected');
    const $pickLocationSelect = $modal.find('select[name="pickLocation"]');
    const $dropLocationSelect = $modal.find('select[name="dropLocation"]');
    const $typeDispatchPickLocation = $modal.find(`input[name=typeDispatchPickLocation]`);
    const $typeDispatchDropLocation = $modal.find(`input[name=typeDispatchDropLocation]`);
    const dropLocationId = $selectedOption.data('drop-location-id');
    const dropLocationLabel = $selectedOption.data('drop-location-label');
    const pickLocationId = $selectedOption.data('pick-location-id');
    const pickLocationLabel = $selectedOption.data('pick-location-label');
    if (pickLocationId) {
        let option = new Option(pickLocationLabel, pickLocationId, true, true);
        $pickLocationSelect.append(option).trigger('change');
    } else {
        $pickLocationSelect.val(null).trigger('change');
    }
    if (dropLocationId) {
        let option = new Option(dropLocationLabel, dropLocationId, true, true);
        $dropLocationSelect.append(option).trigger('change');
    } else {
        $dropLocationSelect.val(null).trigger('change');
    }

    $typeDispatchPickLocation.val($select.val());
    $typeDispatchDropLocation.val($select.val());

    // find all fixed fields that can be configurable
    const $fields = $modal.find('[data-displayed-type]');

    // remove required symbol from all fields
    $fields.find('.required-symbol')
        .remove();
    $fields.find('.data')
        .removeClass('needed')
        .prop('required', false)

    // find all fields that should be displayed
    const $fieldsToDisplay = $fields.filter(`[data-displayed-type~="${$select.val()}"]`);

    // find all fields that should be required
    const $fieldsRequired = $fieldsToDisplay.filter(`[data-required-type~="${$select.val()}"]`);

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
