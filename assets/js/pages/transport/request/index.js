import '@styles/pages/transport/common.scss';
import AJAX, {GET, POST} from "@app/ajax";
import Flash from "@app/flash";
import Modal from "@app/modal";

import {
    initializeForm,
    cancelRequest,
    deleteRequest,
    initializePacking,
    packingOrPrint,
    transportPDF,
} from "@app/pages/transport/request/common";
import {initializeFilters} from "@app/pages/transport/common";

$(function() {
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

    initializePacking(() => {
        table.ajax.reload();
    });

    const $modalTransportRequest = $(`[data-modal-type=new]`);
    const form = initializeForm($modalTransportRequest);
    form
        .onSubmit((data) => {
            submitTransportRequest(form, data, table);
        })
        .onOpen(() => {
            // run after onOpen in initializeForm
            prefillForm($modalTransportRequest);
        });

    if (isPrefillInformationGiven()) {
        $modalTransportRequest.modal('show');
    }

    $(document).arrive('.cancel-request-button', function (){
        $(this).on('click', function(){
            cancelRequest($(this).data('request-id'));
        });
    });

    $(document).arrive('.delete-request-button', function (){
        $(this).on('click', function(){
            deleteRequest($(this).data('request-id'), table);
        });
    });

    $(document).arrive('.print-transport-button', function (){
        $(this).on('click', function(){
            transportPDF($(this).data('request-id'));
        });
    });
});

/**
 * @param {Form} form
 * @param {FormData} data
 */
function submitTransportRequest(form, data, table) {
    const $modal = form.element;
    const $submit = $modal.find('[type=submit]');
    const collectLinked = Boolean(Number(data.get('collectLinked')));
    const printLabels = Boolean(Number(data.get('printLabels')));

    const closeCreationModal = (transportRequest) => {
        $modal.modal('hide');
        table.ajax.reload();

        if(printLabels) {
            packingOrPrint(transportRequest, true);
        }
    };

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
                            .then(({success, message, validationMessage, deliveryId}) => {
                                if (validationMessage) {
                                    Modal.confirm({
                                        message: validationMessage,
                                        validateButton: {
                                            color: 'success',
                                            label: 'Fermer',
                                            click: () => {
                                                closeCreationModal(deliveryId);
                                            }
                                        },
                                        cancelButton: {
                                            hidden: true
                                        },
                                        cancelled: () => {
                                            closeCreationModal(deliveryId);
                                        },
                                    });
                                }
                                else if (success) {
                                    closeCreationModal(deliveryId);
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
    $modal.prepend($(`<input/>`, {
        type: 'hidden',
        class: 'data',
        name: 'delivery',
        val: deliveryData,
    }));
    $modal.prepend($(`<input/>`, {
        type: 'hidden',
        class: 'data',
        name: 'printLabels',
        val: data.get('printLabels'),
    }));

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

function prefillForm($modal) {
    if (isPrefillInformationGiven()) {
        const {content} = GetRequestQuery() || {};
        const formContent = JSON.parse(content || '');
        console.log(formContent);
        if (formContent['Prenom'] || formContent['Nom']) {
            const $contactName = $modal.find('[name=contactName]');
            $contactName.val(`${formContent['Prenom'] || ''}${formContent['Prenom'] ? ' ' :''}${formContent['Nom'] || ''}`)
        }

        if (formContent['nodos']) {
            const $contactFileNumber = $modal.find('[name=contactFileNumber]');
            $contactFileNumber.val(formContent['nodos']);
        }

        if (formContent['Contact']) {
            const $contactContact = $modal.find('[name=contactContact]');
            $contactContact.val(formContent['Contact']);
        }

        if (formContent['Tel']) {
            const $contactContact = $modal.find('[name=contactContact]');
            $contactContact.val($contactContact.val() + ' ' + formContent['Tel']);
        }

        if (formContent['Adresse']) {
            const $contactAddress = $modal.find('[name=contactAddress]');
            $contactAddress.val(formContent['Adresse']);
        }

        if (formContent['CP']) {
            const $contactAddress = $modal.find('[name=contactAddress]');
            $contactAddress.val($contactAddress.val() + ' ' + formContent['CP']);
        }

        if (formContent['Ville']) {
            const $contactAddress = $modal.find('[name=contactAddress]');
            $contactAddress.val($contactAddress.val() + ' ' + formContent['Ville']);
        }

        if (formContent['PersonnesAPrevenir']) {
            const $contactPersonToContact = $modal.find('[name=contactPersonToContact]');
            $contactPersonToContact.val(formContent['PersonnesAPrevenir']);
        }

        if (formContent['Remarques']) {
            const $contactObservation = $modal.find('[name=contactObservation]');
            $contactObservation.val(formContent['Remarques']);
        }
    }
}

function isPrefillInformationGiven() {
    const CLB_API_KEY = $('#CLB_API_KEY').val();
    const {'x-api-key': xApiKey, content} = GetRequestQuery() || {};

    return (
        CLB_API_KEY
        && xApiKey === CLB_API_KEY
        && Object.keys(JSON.parse(content || '')).length > 0
    );
}
