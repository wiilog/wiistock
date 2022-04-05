import '../../../../scss/pages/transport/form.scss';

export function onRequestTypeChange($form, requestType) {
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

export function onTypeChange($form, type) {
    $form
        .find('[data-type]')
        .addClass('d-none');

    $form
        .find('[data-type]')
        .find('[type=checkbox]')
        .prop('checked', false)
        .trigger('change');

    $form.find(`[data-type]`).each(function() {
        const $element = $(this);
        const allowedTypes = $element.data('type');
        if (allowedTypes.some((t) => (t == type))) {
            $element.removeClass('d-none');
        }
    });
}

export function validateNatureForm($form, errors) {
    const $lineContainer = $form.find('.request-line-container');
    const $natureChecks = $lineContainer.find('[name=selected]');
    if (!$natureChecks.filter(':checked').exists()) {
        errors.push({
            elements: [$natureChecks],
            message: `Vous devez sélectionner au moins une nature de colis dans vote demande`,
        });
    }
}

export function onNatureCheckChange($input) {
    const $container = $input.closest('.nature-item');
    const $toDisplay = $container.find('[data-nature-is-selected]');
    if ($input.prop('checked')) {
        $toDisplay.removeClass('d-none');
    }
    else {
        $toDisplay.addClass('d-none');
        const $quantity = $toDisplay.find('[name=quantity]');
        if ($quantity.exists()) {
            $toDisplay.val('');
        }
        else {
            const $temperature = $toDisplay.find('[name=temperature]');
            $temperature
                .val(null)
                .trigger('change');
        }
    }
}
export function cancelRequest($id){
    Modal.confirm({
        ajax: {
            method: 'POST',
            route: 'transport_request_cancel',
            params: {transportRequest: $id},
            },
    message: 'Voulez-vous réellement annuler cette demande de transport ?',
        title: 'Annuler la demande de transport',
        action: {
        color: 'danger',
            label: 'Annuler'
        }
    })
}
