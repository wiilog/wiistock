import Form from "../../../form";
import AJAX from "../../../ajax";
import Flash from "../../../flash";
import {onRequestTypeChange, onTypeChange} from "./form";

import '../../../../scss/pages/transport.scss';

$(function() {
    const $modalNewTransportRequest = $("#modalNewTransportRequest");

    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_TRANSPORT_REQUESTS);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

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
            submitTransportRequest(form, data);
        });

    initializeNewForm($modalNewTransportRequest);

    $(`.filters [name="category"] + label, .filters [name="type"] + label`).on(`click`, function(event) {
        const $label = $(this);
        const $input = $label.prev();
        if($input.is(`:checked`)) {
            event.preventDefault();
            event.stopPropagation();

            $input.prop(`checked`, false);
            if ($input.attr('name') === 'category') {
                $(`.filters [name="type"] + label`).removeClass(`d-none`).addClass(`d-inline-flex`);
            }
        }
    });

    $(`.filters [name="category"]`).on(`change`, function() {
        const category = $(this).val();
        const $filters = $(`.filters`);

        $filters.find(`[name="type"]:not([data-category="${category}"])`).prop(`checked`, false);
        $filters.find(`[name="type"] + label`).addClass(`d-none`).removeClass(`d-inline-flex`);
        $filters.find(`[name="type"][data-category="${category}"] + label`).removeClass(`d-none`).addClass(`d-inline-flex`);
    })
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

