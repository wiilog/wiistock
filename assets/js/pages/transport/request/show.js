import '@styles/pages/transport/show.scss';
import AJAX, {GET, POST} from "@app/ajax";
import Flash, {ERROR, SUCCESS} from "@app/flash";
import {initializeForm, cancelRequest, initializePacking, deleteRequest, transportPDF} from "@app/pages/transport/request/common";
import {getPacks, getStatusHistory, getTransportHistory} from "@app/pages/transport/common";


$(function () {
    const transportRequest = $(`input[name=transportId]`).val();
    const transportType = $(`input[name=transportType]`).val();

    getStatusHistory(transportRequest, transportType);
    getTransportHistory(transportRequest, transportType);
    getPacks(transportRequest, transportType);

    initializePacking(() => {
        getPacks(transportRequest, transportType);
        getStatusHistory(transportRequest, transportType);
        getTransportHistory(transportRequest, transportType);
    });

    $('.cancel-request-button').on('click', function(){
        cancelRequest($(this).data('request-id'));
    });

    $('.delete-request-button').on('click', function(){
        deleteRequest($(this).data('request-id'));
    });

    $('.print-transport-button').on('click', function(){
        transportPDF($(this).data('request-id'));
    });

    $('.edit-button').on('click', function(){
        openEditModal($(this));
    });
});

/**
 * @param {Form} form
 * @param {FormData} data
 */
function submitTransportRequestEdit(form, data) {
    const $modal = form.element;
    const $submit = $modal.find('[type=submit]');

    const $transportRequest = $modal.find('[name=transportRequest]');
    const transportRequest = $transportRequest.val();
    const printLabels = Boolean(Number(data.get('printLabels')));

    wrapLoadingOnActionButton($submit, () => {
        return AJAX
            .route(POST, 'transport_request_edit', {transportRequest})
            .json(data)
            .then((editResponse) => {
                const {success, createdPacks, message} = editResponse;
                Flash.add(
                    success ? 'success' : 'danger',
                    message || `Une erreur s'est produite`
                );
                if (success && printLabels && createdPacks && createdPacks.length > 0) {
                    return AJAX
                        .route(GET, `print_transport_packs`, {
                            transportRequest,
                            packs: createdPacks.join(',')
                        })
                        .file({
                            success: "Vos étiquettes ont bien été téléchargées",
                            error: "Erreur lors de l'impression des étiquettes"
                        })
                        .then(() => editResponse);
                }
                return editResponse;
            })
            .then(({success}) => {
                if (success) {
                    $modal.modal('hide');
                    window.location.reload();
                }
            });
    });
}

function openEditModal($button) {
    const $oldEditModal = $('[data-modal-type="edit"]');
    $oldEditModal.remove();

    const transportRequest = $button.data('request-id');
    $button.pushLoader(`black`);

    AJAX.route(GET, 'transport_request_edit_api', {transportRequest})
        .json()
        .then(({template}) => {
            const $modal = $(template);
            $('body').append($modal);

            const form = initializeForm($modal, true);
            form.onSubmit((data) => {
                submitTransportRequestEdit(form, data);
            });

            $modal.modal('show');
            $button.popLoader();
        })
        .catch(() => {
            $button.popLoader();
        });
}
