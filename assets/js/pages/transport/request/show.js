import '@styles/pages/transport/show.scss';
import "@app/pages/transport/common";
import {initializeForm, cancelRequest, deleteRequest} from "@app/pages/transport/request/common";
import AJAX, {GET, POST} from "@app/ajax";
import Flash from "@app/flash";
import {saveAs} from "file-saver";

$(function () {
    const transportRequest = $(`input[name=transportRequestId]`).val();

    $('.cancel-request-button').on('click', function(){
        cancelRequest($(this).data('request-id'));
    });

    $('.delete-request-button').on('click', function(){
        deleteRequest($(this).data('request-id'));
    });


    $(`.print-barcodes`).on(`click`, function() {
        const isPacked = $(this).data(`is-packed`);
        if(isPacked) {
            printBarcodes($(this), transportRequest)
        } else {
            alert("Modale de colisage"); // TODO A faire
        }
    });

    const $modals = $("#modalTransportDeliveryRequest, #modalTransportCollectRequest");
    $modals.each(function() {
        const $modal = $(this);
        const form = initializeForm($modal, true);
        form.onSubmit((data) => {
            submitTransportRequestEdit(form, data);
        });
    });
});

function submitTransportRequestEdit(form, data) {
    const $modal = form.element;
    const $submit = $modal.find('[type=submit]');

    const $transportRequest = $modal.find('[name=transportRequest]');
    const transportRequest = $transportRequest.val();

    wrapLoadingOnActionButton($submit, () => {
        return AJAX
            .route(POST, 'transport_request_edit', {transportRequest})
            .json(data)
            .then(({success, message}) => {
                if (success) {
                    $modal.modal('hide');
                }
                Flash.add(
                    success ? 'success' : 'danger',
                    message || `Une erreur s'est produite`
                );
                window.location.reload();
            });
    });
}

function printBarcodes($button, transportRequest) {
    wrapLoadingOnActionButton($button, () => {
        Flash.add(`info`, `Génération des étiquettes de colis en cours`);
        return AJAX.route(GET, `print_transport_packs`, {transportRequest})
            .raw()
            .then(response => response.blob())
            .then((response) => {
                saveAs(response, "ETQ_transport");
            });
    });
}
