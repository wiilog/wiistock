import Form from "@app/form";
import { POST } from "@app/ajax";

let tableShippings;

$(function() {
    initTableShippings();
    initModalNewShippingRequest()
})


function initTableShippings() {
    let initialVisible = $(`#tableShippings`).data(`initial-visible`);
    if (!initialVisible) {
        return $
            .post(Routing.generate('shipping_api_columns'))
            .then(columns => proceed(columns));
    } else {
        return new Promise((resolve) => {
            resolve(proceed(initialVisible));
        });
    }
    function proceed(columns) {
        let tableShippingsConfig = {
            pageLength: 10,
            processing: true,
            serverSide: true,
            paging: true,
            ajax: {
                url: Routing.generate('shipping_api', true),
                type: "POST",
            },
            rowConfig: {
                needsRowClickAction: true,
            },
            columns,
            hideColumnConfig: {
                columns,
                tableFilter: 'tableShippings'
            },
            drawConfig: {
                needsSearchOverride: true,
            },
            page: 'shipping-request',
        };
        tableShippings = initDataTable('tableShippings', tableShippingsConfig);
        return tableShippings;
    }
}

function initModalNewShippingRequest() {
    const $modal = $('#modalNewShippingRequest')

    // pre-filling phone select according to the applicant
    const $requestersSelect = $modal.find('select[name="requesters"]')
    $modal.on('show.bs.modal', function (event) {
        $requestersSelect.trigger('change');
    })
    $requestersSelect.on('change', () => {
        const $requesterPhoneInput = $('select[name="requesterPhoneNumbers"]')
        const requestersData = $requestersSelect.select2('data');
        $requesterPhoneInput.find('option[data-from-user="1"]').remove();
        Object.entries(requestersData).forEach(([key, value]) => {
            const phone = value.phone || $(value.element).data('phone');
            if (phone) {
                $requesterPhoneInput.append(`<option value="${phone}" data-from-user="1" selected>${phone}</option>`)
            }
        })
    });

    // pre-filling customer information according to the customer
    const $customersSelect = $modal.find('select[name="customerName"]')
    $customersSelect.on('change', () => {
       const customerData = $customersSelect.select2('data');
       $modal.find('input[name="customerPhone"]').val(customerData[0]?.phoneNumber);
       $modal.find('input[name="customerRecipient"]').val(customerData[0]?.recipient);
       $modal.find('input[name="customerAddress"]').val(customerData[0]?.address);
    });

    Form
        .create($modal)
        .submitTo(POST, 'shipping_request_form_submit', {success: (data) => {
            if(data.success) {
                // TODO uncomment after 9575
                // window.location.href = Routing.generate('shipping_request_show', {id: data.shippingRequestId});
            }
        }});
}

