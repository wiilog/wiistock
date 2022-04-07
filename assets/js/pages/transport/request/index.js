import AJAX, {GET, POST} from "@app/ajax";
import Flash from "@app/flash";
import {initializeForm, cancelRequest, deleteRequest} from "@app/pages/transport/request/common";
import {initializeFilters} from "@app/pages/transport/common";

$(function() {
    const $modalTransportRequest = $("#modalTransportRequest");

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

    const form = initializeForm($modalTransportRequest);
    form.onSubmit((data) => {
        submitTransportRequest(form, data, table);
    });

    $(document).arrive('.cancel-request-button', function (){
        $(this).on('click', function(){
            cancelRequest($(this).data('request-id'));
        });
    });

    $(document).arrive('.delete-request-button', function (){
        $(this).on('click', function(){
            deleteRequest($(this).data('request-id'));
        });
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
                                        validateButton: {
                                            color: 'success',
                                            label: 'Fermer',
                                            click: () => {
                                                $modal.modal('hide');
                                            }
                                        },
                                        cancelButton: {
                                            hidden: true
                                        },
                                        cancelled: () => {
                                            $modal.modal('hide');
                                        },
                                    });
                                }
                                else if (success) {
                                    $modal.modal('hide');
                                    table.ajax.reload();
                                }
                                Flash.add(
                                    success ? 'success' : 'danger',
                                    message || `Une erreur s'est produite`
                                );
                            });
                    }
                    return false;
                });
        });
    }
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
                            validateButton: {
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
