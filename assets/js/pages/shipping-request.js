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
}

