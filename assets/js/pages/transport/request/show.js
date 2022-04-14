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
        printBarcodes($(this), transportRequest)
    });

    getStatusHistory(transportRequest);
    getTransportHistory(transportRequest);
    getPacks(transportRequest);

    const $modals = $("#modalTransportDeliveryRequest, #modalTransportCollectRequest");
    $modals.each(function() {
        const $modal = $(this);
        const form = initializeForm($modal, true);
        form.onSubmit((data) => {
            submitTransportRequestEdit(form, data);
        });
    });
});

function getStatusHistory(transportRequest) {
    $.get(Routing.generate(`transport_request_status_history_api`, {transportRequest}, true))
        .then(({template}) => {
            const $statusHistoryContainer = $(`.status-history-container`);
            $statusHistoryContainer.empty().append(template);
            $statusHistoryContainer.animate({
                scrollTop: $statusHistoryContainer.find(`.last-status-history`).offset().top
            }, 1000);
        });
}

function getTransportHistory(transportRequest) {
    $.get(Routing.generate(`transport_history_api`, {transportRequest}, true))
        .then(({template}) => {
            const $transportHistoryContainer = $(`.transport-history-container`);
            $transportHistoryContainer.empty().append(template);
        });
}

function getPacks(transportRequest) {
    $.get(Routing.generate(`transport_packs_api`, {transportRequest}, true))
        .then(({template}) => {
            const $packsContainer = $(`.packs-container`);
            $packsContainer.empty().append(template);
        });
}

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
                table.ajax.reload();
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
                saveAs(response);
            });
    });
}
