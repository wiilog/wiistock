import '@styles/pages/transport/form.scss';
import Form from "@app/form";


export function initializeForm($form, editForm = false) {
    const form = Form
        .create($form)
        .addProcessor((_, errors, $form) => {
            validateNatureForm($form, errors)
        })
        .onOpen(() => {
            onFormOpened(form, editForm);
        });

    form
        .on('change', '[name=requestType]', function () {
            const $requestType = $(this);
            onRequestTypeChange($requestType.closest('.modal'), $requestType.val());
        })
        .on('change', '[name=type]', function () {
            const $type = $(this);
            onTypeChange($type.closest('.modal'), $type.val());
        })
        .on('change', '.nature-item [name=selected]', function () {
            onNatureCheckChange($(this));
        });

    return form;
}

function onFormOpened(form, editForm) {
    const $modal = form.element;

    $modal.find('delivery').remove();
    const $requestType = $modal.find('[name=requestType]');
    $requestType
        .prop('checked', false)
        .prop('disabled', false);

    const $type = $modal.find('[name=type]');
    $type
        .prop('checked', false)
        .prop('disabled', false);

    $requestType
        .filter('[value=collect]')
        .prop('checked', true)
        .trigger('change');

    if (!editForm) {
        $modal
            .find('.contact-container .data, [name=expectedAt]')
            .prop('disabled', false);
    }
}

function onNatureCheckChange($input) {
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

function validateNatureForm($form, errors) {
    const $lineContainer = $form.find('.request-line-container');
    const $natureChecks = $lineContainer.find('[name=selected]');
    if (!$natureChecks.filter(':checked').exists()) {
        errors.push({
            elements: [$natureChecks],
            message: `Vous devez sélectionner au moins une nature de colis dans vote demande`,
        });
    }
}
function onRequestTypeChange($form, requestType) {
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

function onTypeChange($form, type) {
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

export function cancelRequest(transportRequest){
    Modal.confirm({
        ajax: {
            method: 'POST',
            route: 'transport_request_cancel',
            params: {transportRequest},
        },
        message: 'Voulez-vous réellement annuler cette demande de transport ?',
        title: 'Annuler la demande de transport',
        validateButton: {
            color: 'danger',
            label: 'Annuler',
        },
        cancelButton: {
            label: 'Fermer',
        }
    });
}

export function deleteRequest(transportRequest){
    Modal.confirm({
        ajax: {
            method: 'DELETE',
            route: 'transport_request_delete',
            params: {transportRequest},
        },
        message: 'Voulez-vous réellement supprimer cette demande de transport ?',
        title: 'Supprimer la demande de transport',
        validateButton: {
            color: 'danger',
            label: 'Supprimer'
        }
    });
}
