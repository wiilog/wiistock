import Form from "../../../form";
import AJAX from "../../../ajax";
import Flash from "../../../flash";
import {onRequestTypeChange} from "./form";

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
        .onSubmit((data) => {
            submitTransportRequest(form, data);
        });

    initializeNewForm($modalNewTransportRequest);
});

function submitTransportRequest(form, data) {
    const $submit = form.element.find('[type=submit]');
    $submit.pushLoader('white');

    AJAX.route(`POST`, 'transport_request_new')
        .json(data)
        .then(({success, message, redirect}) => {
            $submit.popLoader();
            Flash.add(
                success ? 'success' : 'danger',
                message || `Une erreur s'est produite`
            );
            // TODO hide modale
        });
}

function initializeNewForm($form) {
    $form.find('[name=requestType]').on('change', function () {
        onRequestTypeChange($(this));
    });
}

