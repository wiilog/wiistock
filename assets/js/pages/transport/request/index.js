import Form from "../../../form";
import AJAX from "../../../ajax";
import Flash from "../../../flash";
import {onRequestTypeChange} from "./form";

$(function() {
    const $modalNewTransportRequest = $("#modalNewTransportRequest");

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

