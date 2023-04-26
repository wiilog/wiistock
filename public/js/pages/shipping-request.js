let tableShippings;

$(function() {
    // TODO DECOMMENTER
    // initTableShippings();
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
            lengthMenu: [5, 10, 25],
            processing: true,
            serverSide: true,
            paging: true,
            ajax: {
                url: Routing.generate('shipping_api_columns', true),
                type: "POST",
            },
            columns: [
                {data: 'status', title: 'Statut'},
                {data: 'createdAt', title: 'Date de création'},
                {data: 'requestCaredAt', title: 'Date de prise en charge souhaitée'},
                {data: 'validatedAt', title: 'Date de validation'},
                {data: 'plannedAt', title: 'Date de planification'},
                {data: 'expectedPickedAt', title: 'Date d\'enlèvement prévu'},
                {data: 'treatedAt', title: 'Date d\'expédition'},
                {data: 'requesters', title: 'Demandeur'},
                {data: 'customerOrderNumber', title: 'N° commande client'},
                {data: 'customerName', title: 'Client'},
                {data: 'carrier', title: 'Transporteur'},
            ],
            rowConfig: {
                needsRowClickAction: true,
            },

        };
        tableShippings = initDataTable('tableShippings', tableShippingsConfig);
        return arrivalsTable;
    }
}

function initModalNewShippingRequest(){
    const $modal = $('#modalNewShippingRequest')

    // pre-filling phone select according to the applicant
    const $requestersSelect = $modal.find('select[name="requesters"]')
    $modal.on('show.bs.modal', function (event) {
        $requestersSelect.trigger('change');
    })
    $requestersSelect.on('change', ()=> {
        const $requesterPhoneInput = $('select[name="requesterPhoneNumbers"]')
        const requestersData = $requestersSelect.select2('data');
        $requesterPhoneInput.find('option[data-from-user="1"]').remove();
        Object.entries(requestersData).forEach(([key, value]) => {
            const phone = value.phone || $(value.element).data('phone');
            if (phone ) {
                $requesterPhoneInput.append(`<option value="${phone}" data-from-user="1" selected>${phone}</option>`)
            }
        })
    });
}
