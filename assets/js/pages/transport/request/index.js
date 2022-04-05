import Form from "../../../form";
import AJAX, {GET, POST} from "../../../ajax";
import Flash from "../../../flash";
import {onRequestTypeChange, onTypeChange, validateNatureForm, onNatureCheckChange, cancelRequest} from "./common";

import {initializeFilters} from "../common";

$(function() {
    const $modalNewTransportRequest = $("#modalNewTransportRequest");

    initializeFilters(PAGE_TRANSPORT_REQUESTS)

    let table = initDataTable('tableTransportRequests', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        pageLength: 24,
        lengthMenu: [24, 48, 72, 96],
        ajax: {
            url: Routing.generate(`transport_request_api`),
            type: "POST",
            data: data => {
                data.dateMin = $(`.filters [name="dateMin"]`).val();
                data.dateMax = $(`.filters [name="dateMax"]`).val();
            }
        },
        domConfig: {
            removeInfo: true
        },
        //remove empty div with mb-2 that leaves a blank space
        drawCallback: () => $(`.row.mb-2 .col-auto.d-none`).parent().remove(),
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {data: 'content', name: 'content', orderable: false},
        ],
    });

    const form = Form
        .create($modalNewTransportRequest)
        .addProcessor((_, errors, $form) => {
            validateNatureForm($form, errors)
        })
        .onOpen(() => {
            onOpenTransportRequestForm(form);
        })
        .on('change', '.nature-item [name=selected]', function () {
            onNatureCheckChange($(this));
        })
        .onSubmit((data) => {
            submitTransportRequest(form, data, table);
        });

    initializeNewForm($modalNewTransportRequest);

    $(document).arrive('.cancel-request', function (){
        $(this).on('click', function(){
            cancelRequest($(this).data('transport-request-id'));
        })
    });
});

/**
 * @param {Form} form
 * @param {FormData} data
 * @param data
 */
function submitTransportRequest(form, data, table) {
    const $modal = form.element;
    const $submit = $modal.find('[type=submit]');
    const collectLinked = Boolean(Number(data.get('collectLinked')));

    if (collectLinked) {
        saveDeliveryForLinkedCollect($modal, data);
    }
    else {
        wrapLoadingOnActionButton($submit, () => {
            return canSubmit($modal)
                .then((can) => {
                    if (can) {
                        return AJAX.route(POST, 'transport_request_new')
                            .json(data)
                            .then(({success, message, validationMessage}) => {
                                if (validationMessage) {
                                    Modal.confirm({
                                        message: validationMessage,
                                        action: {
                                            color: 'success',
                                            label: 'Fermer',
                                            click: () => {
                                                $modal.modal('hide');
                                            }
                                        },
                                        cancelled: () => {
                                            $modal.modal('hide');
                                        },
                                        discard: false,
                                    });
                                }
                                else {
                                    $modal.modal('hide');
                                }
                                Flash.add(
                                    success ? 'success' : 'danger',
                                    message || `Une erreur s'est produite`
                                );
                                table.ajax.reload();
                            });
                    }
                    return false;
                });
        });
    }
}

function initializeNewForm($form) {
    $form.find('[name=requestType]').on('change', function () {
        const $requestType = $(this);
        onRequestTypeChange($requestType.closest('.modal'), $requestType.val());
    });
    $form.find('[name=type]').on('change', function () {
        const $type = $(this);
        onTypeChange($type.closest('.modal'), $type.val());
    });
}

function onOpenTransportRequestForm(form) {
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

    $modal.find('.contact-container .data, [name=expectedAt]')
        .prop('disabled', false);

    $requestType
        .filter('[value=collect]')
        .prop('checked', true)
        .trigger('change')
}

function saveDeliveryForLinkedCollect($modal, data) {
    const deliveryData = JSON.stringify(data.asObject());
    const $deliveryData = $(`<input type="hidden" class="data" name="delivery"/>`);
    $deliveryData.val(deliveryData);
    $modal.prepend($deliveryData);

    const $requestType = $modal.find('[name=requestType]');
    $requestType
        .prop('checked', false)
        .prop('disabled', true);
    $requestType
        .filter('[value=collect]')
        .prop('checked', true)
        .trigger('change');

    $modal
        .find('.contact-container .data')
        .prop('disabled', true);

    const [deliveryExpectedAt] = data.get('expectedAt').split('T');
    $modal
        .find('[data-request-type=collect] [name=expectedAt]')
        .val(deliveryExpectedAt)
        .prop('disabled', true);
}

function canSubmit($form) {
    const $fileNumber = $form.find('[name=contactFileNumber]');
    const $requestType = $form.find('[name=requestType]:checked');
    const requestType = $requestType.val();

    if (requestType === 'collect') {
        return AJAX.route(GET, 'transport_request_collect_already_exists', {fileNumber: $fileNumber.val()})
            .json()
            .then(({exists}) => {
                if (exists) {
                    return new Promise((resolve) => {
                        Modal.confirm({
                            message: `Il existe déjà une demande de collecte en cours pour ce patient.
                                    Cliquez sur "Continuer" pour valider quand même sa création`,
                            action: {
                                color: 'success',
                                label: 'Continuer',
                                click: () => {
                                    resolve(true);
                                }
                            },
                            cancelled: () => {
                                resolve(false);
                            }
                        });
                    });
                }
                else {
                    return new Promise(((resolve) => {resolve(true)}));
                }
            })
    }
    else {
        return new Promise(((resolve) => {resolve(true)}))
    }
}
