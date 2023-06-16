import '@styles/pages/transport/form.scss';
import Form from "@app/form";
import Modal from "@app/modal";
import AJAX, {GET, POST} from "@app/ajax";
import Flash, {ERROR, SUCCESS} from "@app/flash";

export function initializeForm($form, editForm = false) {
    const form = Form
        .create($form, {clearOnOpen: !editForm})
        .addProcessor((_, errors, $form) => {
            validateNatureForm($form, errors)
        })
        .onOpen(() => {
            resetForm(form);
        })
        .onClose(() => {
            clearForm(form, editForm);
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

export function initializePacking(submitCallback) {
    const $modalPacking = $('#modalTransportRequestPacking');
    $(document).on("click", ".print-request-button", function() {
        const $button = $(this);
        wrapLoadingOnActionButton($button, () => packingOrPrint($button.data('request-id')));
    });

    Form.create($modalPacking).onSubmit(function(data) {
        wrapLoadingOnActionButton($modalPacking.find('[type=submit]'), () => {
            return submitPackingModal($modalPacking, data, () => {
                submitCallback();
            });
        });
    })
}

export function packingOrPrint(transportRequest, force = false) {
    if (!force) {
        return AJAX.route(POST, `transport_request_packing_check`, {transportRequest})
            .json()
            .then((result) => {
                if (result.success) {
                    return openPackingModal(transportRequest);
                }
                else {
                    return printBarcodes(transportRequest);
                }
            });
    }
    else {
        return openPackingModal(transportRequest);
    }
}

export function openPackingModal(transportRequest) {
    const $modal = $('#modalTransportRequestPacking');
    const $modalBody = $modal.find('.modal-body');
    $modalBody.html(`
        <div class="row justify-content-center">
             <div class="col-auto">
                <div class="spinner-border" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
             </div>
        </div>
    `);
    $modal.modal('show');
    return AJAX.route(GET, `transport_request_packing_api`, {transportRequest})
        .json()
        .then((result) => {
            if (result && result.success) {
                $modalBody.html(result.html);
            }
        });
}

export function printBarcodes(transportRequest) {
    Flash.add(`info`, `Génération des étiquettes d'UL en cours`);
    return AJAX.route(GET, `print_transport_packs`, {transportRequest})
        .file({
            success: "Vos étiquettes ont bien été téléchargées",
            error: "Erreur lors de l'impression des étiquettes"
        });
}


export function submitPackingModal($modalPacking, data, callback) {
    const transportRequest = data.get('request');
    data.delete('request');
    return AJAX.route(POST, `transport_request_packing`, {transportRequest})
        .json(data)
        .then((result) => {
            if (result.success === true) {
                const printing = printBarcodes(transportRequest);
                printing.then(() => {
                    $modalPacking.modal('hide');
                    callback();
                });
                return printing;
            }
            else {
                Flash.add(ERROR, result.message || 'Une erreur est survenue lors du colisage');
            }
        });
}

function clearForm(form, editForm) {
    const $modal = form.element;

    $modal.find('[name=delivery][type=hidden]').remove();
    $modal.find('[name=printLabels][type=hidden]').remove();
    const $requestType = $modal.find('[name=requestType]');
    $requestType
        .prop('checked', false)
        .prop('disabled', false);

    const $type = $modal.find('[name=type]');
    $type
        .prop('checked', false)
        .prop('disabled', false);

    if (!editForm) {
        $modal
            .find('.contact-container .data, [name=expectedAt]')
            .prop('disabled', false);
    }
}

function resetForm(form) {
    const $modal = form.element;
    const $requestType = $modal.find('[name=requestType]');
    if ($requestType.is(':not(input[type=hidden])')) {
        $requestType
            .filter('[value=delivery]')
            .prop('checked', true)
            .trigger('change');
    }

    const $type = $modal.find('[name=type]')
    if ($type.is('input[type=hidden]')) {
        onTypeChange($modal, $type.val(), false);
    }
}

function onNatureCheckChange($input) {
    const $container = $input.closest('.nature-item');
    const $toDisplay = $container.find('[data-nature-is-selected]');
    const $textInfo = $('#text-info');
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

    if ($('.nature-item-wrapper input[type=checkbox]:checked').exists()) {
        $textInfo.removeClass('d-none');
    }
    else {
        $textInfo.addClass('d-none');
    }
}

function validateNatureForm($form, errors) {
    const $lineContainer = $form.find('.request-line-container');
    const $natureChecks = $lineContainer.find('[name=selected]');
    if (!$natureChecks.filter(':checked').exists()) {
        errors.push({
            elements: [$natureChecks],
            message: `Vous devez sélectionner au moins une nature dans vote demande`,
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
    $form.find(`.warning-empty-natures`).addClass(`d-none`);

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

function onTypeChange($form, type, resetValues = true) {
    $form
        .find('[data-type]')
        .addClass('d-none');

    if (resetValues) {
        $form
            .find('[data-type]')
            .find('[type=checkbox]')
            .prop('checked', false)
            .trigger('change');
    }

    $form.find(`[data-type]`).each(function () {
        const $element = $(this);
        const allowedTypes = $element.data('type');
        if (allowedTypes.some((t) => (t == type))) {
            $element.removeClass('d-none');
        }
    });

    const $container = $form.find('.warning-empty-natures');
    if ($('.nature-item:not(.d-none)').length === 0) {
        $container.removeClass('d-none');
        $form.find('button[type=submit]').prop("disabled" ,true);
        $container.parent().addClass('justify-content-center');
    }
    else {
        $form.find('button[type=submit]').prop("disabled" ,false);
        $container.addClass('d-none');
        $container.parent().removeClass('justify-content-center')
    }
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

export function deleteRequest(transportRequest, table = null){
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
        },
        table: table,
    });
}

export function transportPDF(transportId){
    Wiistock.download(Routing.generate('print_transport_note', {transportRequest: transportId}));
}
