import Form from "../../../form";
import AJAX from "../../../ajax";
import Flash from "../../../flash";
import {onRequestTypeChange, onTypeChange} from "./form";

$(function() {
    const $modalNewTransportRequest = $("#modalNewTransportRequest");

    const form = Form
        .create($modalNewTransportRequest)
        .onClose(() => {
            onCloseTransportRequestForm(form);
        })
        .onSubmit((data) => {
            submitTransportRequest(form, data);
        });

    initializeNewForm($modalNewTransportRequest);
});

/**
 * @param {Form} form
 * @param {FormData} data
 */
function submitTransportRequest(form, data) {
    const $modal = form.element;
    const $submit = $modal.find('[type=submit]');
    const collectLinked = Boolean(Number(data.get('collectLinked')));

    if (collectLinked) {
        saveDeliveryForLinkedCollect($modal, data);
    }
    else {
        $submit.pushLoader('white');
        AJAX.route(`POST`, 'transport_request_new')
            .json(data)
            .then(({success, message}) => {
                $submit.popLoader();
                Flash.add(
                    success ? 'success' : 'danger',
                    message || `Une erreur s'est produite`
                );
            });
    }
}

function initializeNewForm($form) {
    $form.find('[name=requestType]').on('change', function () {
        onRequestTypeChange($(this));
    });
    $form.find('[name=type]').on('change', function () {
        onTypeChange($(this));
    });
}

function onCloseTransportRequestForm(form) {
    const $modal = form.element;

    $modal.find('delivery').remove();
    const $requestType = $modal.find('[name=requestType]');
    $requestType
        .prop('checked', false)
        .prop('disabled', false);

    $modal.find('.contact-container .data, [name=expectedAt]')
        .prop('disabled', false);
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
        .filter('[value=collect]').prop('checked', true)
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

