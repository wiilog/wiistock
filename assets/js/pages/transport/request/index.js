import Form from "../../../form";
import AJAX from "../../../ajax";
import Flash from "../../../flash";
import {onRequestTypeChange, onTypeChange} from "./form";

import {initializeFilters} from "../common";

$(function() {
    const $modalNewTransportRequest = $("#modalNewTransportRequest");

    initializeFilters(PAGE_TRANSPORT_REQUESTS)

    let table = initDataTable('tableTransportRequests', {
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        ajax: {
            url: Routing.generate(`transport_request_api`),
            type: "POST"
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
        .onClose(() => {
            onCloseTransportRequestForm(form);
        })
        .onSubmit((data) => {
            submitTransportRequest(form, data, table);
        });

    initializeNewForm($modalNewTransportRequest);
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
            return AJAX.route(`POST`, 'transport_request_new')
                .json(data)
                .then(({success, message}) => {
                    Flash.add(
                        success ? 'success' : 'danger',
                        message || `Une erreur s'est produite`
                    );
                    $modal.modal('hide');
                    table.ajax.reload();
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

function onCloseTransportRequestForm(form) {
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

    onRequestTypeChange($modal, null);
    onTypeChange($modal, null);
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

