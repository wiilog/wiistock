import {POST} from "@app/ajax";

export function initModalFormShippingRequest($modal, submitRoute, onSuccess)  {
    const $requestersSelect = $modal.find('[name=requesters]')
    const $customersSelect = $modal.find('[name=customerName]')
    const form = Form
        .create($modal)
        .submitTo(POST, submitRoute, {success: (data) => {
                if(data.success) {
                    onSuccess(data);
                }
            }})
        .onOpen(()=>{
            // pre-filling phone select according to the applicant
            $requestersSelect.trigger('change');
        })
        .on('change', $requestersSelect, () => {
            const $requesterPhoneInput = $('[name=requesterPhoneNumbers]')
            const requestersData = $requestersSelect.select2('data');
            $requesterPhoneInput.find('[data-from-user=1]').remove();
            Object.entries(requestersData).forEach(([key, value]) => {
                const phone = value.phone || $(value.element).data('phone');
                if (phone) {
                    $requesterPhoneInput.append($('<option>', {value: phone, 'data-from-user': 1, selected: true}).text(phone));
                }
            })
        })
        .on('change', $customersSelect, () => {
            // pre-filling customer information according to the customer
            const customerData = $customersSelect.select2('data');
            $modal.find('[name=customerPhone]').val(customerData[0]?.phoneNumber);
            $modal.find('[name=customerRecipient]').val(customerData[0]?.recipient);
            $modal.find('[name=customerAddress]').val(customerData[0]?.address);
        });
}
