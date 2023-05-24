import {POST} from "@app/ajax";
import Form from "@app/form";
import Modal from "@app/modal";

export function initModalFormShippingRequest($modal, submitRoute, onSuccess) {
    Form
        .create($modal)
        .submitTo(POST, submitRoute, {success: (data) => {
            if(data.success) {
                onSuccess(data);
            }
        }})
        .onOpen(() => {
            Modal.load('shipping_request_form', {id: $modal.data('shipping-request-id') || ''}, $modal, $modal.find('.modal-body'));
        })
        .on('change', '[name=customerName]', (event) => {
            const $customers = $(event.target)
            // pre-filling customer information according to the customer
            const [customer] = $customers.select2('data');
            $modal.find('[name=customerPhone]').val(customer?.phoneNumber);
            $modal.find('[name=customerRecipient]').val(customer?.recipient);
            $modal.find('[name=customerAddress]').val(customer?.address);
        });

    $modal
        .off('select2:select.form')
        .off('select2:unselect.form')
        .on('select2:select', '[name=requesters]', (event) => {
            const {data} = event.params;
            if (data) {
                addPhoneNumber($modal, data);
            }
        })
        .on('select2:unselect', '[name=requesters]', (event) => {
            const {data} = event.params;
            if (data) {
                removePhoneNumber($modal, data);
            }
        });
}

function addPhoneNumber($modal, requesterData) {
    const $requesterPhoneInput = $modal.find('[name=requesterPhoneNumbers]');
    const {phone} = requesterData;
    if (phone) {
        $requesterPhoneInput.append($('<option>', {
            value: phone,
            'data-from-user': 1,
            selected: true,
            text: phone,
        }));
    }
}

function removePhoneNumber($modal, requesterData) {
    const $requesterPhoneInput = $modal.find('[name=requesterPhoneNumbers]');
    const {phone} = requesterData;
    if (phone) {
        $requesterPhoneInput.find(`[value=${phone}]`).remove();
        $requesterPhoneInput.trigger('change');
    }
}
