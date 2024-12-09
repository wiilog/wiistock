import {DELETE, POST} from "@app/ajax";
import Form from "@app/form";
import Camera from "@app/camera";
import Modal from "@app/modal";
import Routing from '@app/fos-routing';
import {updateRequiredMark} from "@app/utils";

export function displayAttachmentRequired($select) {
    const $modal = $select.closest(`.modal`);
    const statusData = $select.select2(`data`)[0];
    const requiredAttachment = statusData && statusData.requiredAttachment ? 1 : 0;

    $modal.find(`[name=isFileNeeded]`).val(requiredAttachment);
    $modal.find(`[name=isSheetFileNeeded]`).val(requiredAttachment);

    const $labelContainer = $modal.find(`.attachment-label span`).first();
    updateRequiredMark($labelContainer, requiredAttachment);
}

export function openModalUpdateProductionRequestStatus($container, $modalUpdateProductionRequestStatus, productionRequest, successCallback){
    Form.create($modalUpdateProductionRequestStatus, {clearOnOpen: true})
        .clearOpenListeners()
        .onOpen(() => {
            Modal.load(`production_request_update_status_content`,
                {
                    productionRequest,
                },
                $modalUpdateProductionRequestStatus,
                $modalUpdateProductionRequestStatus.find(`.modal-body`),
                {
                    onOpen: () => {
                        Camera.init(
                            $modalUpdateProductionRequestStatus.find(`.take-picture-modal-button`),
                            $modalUpdateProductionRequestStatus.find(`[name="files[]"]`)
                        );
                    },
                },
            );
        })
        .submitTo(POST, `production_request_update_status`, {
            routeParams: {
                productionRequest,
            },
            success: (response) => {
                if(response.needModalConfirmationForGenerateDispatch) {
                    modalConfirmDeleteProductionRequest(productionRequest)
                } else {
                    successCallback()
                }
            }
        });

    $modalUpdateProductionRequestStatus.modal(`show`);
}

export function modalConfirmDeleteProductionRequest(productionRequestId){
    Modal.confirm({
        title: "Créer un acheminement",
        message: `Voulez-vous générer une demande d'acheminement ?`,
        validateButton: {
            label: 'Créer la demande',
            click: () => {
                redirectAfterValidateConfirmModal(productionRequestId);
            },
        },
        cancelButton: {
            label: 'Annuler',
        },
    })
}

function redirectAfterValidateConfirmModal(productionRequestId){
    window.location.href = Routing.generate('production_request_show', {'id' : productionRequestId, 'open-modal': 'new'})
}

export function initDeleteProductionRequest(){
    $(document).on('click', '.delete-production-request', function(){
        const id = $(this).data('id');
        Modal.confirm({
            ajax: {
                method: DELETE,
                route: `production_request_delete`,
                params: {productionRequest: id},
            },
            message: `Voulez-vous réellement supprimer cette demande de production ?`,
            title: `Supprimer la demande de production`,
            validateButton: {
                color: `danger`,
                label: `Supprimer`,
            },
        })
    });
}

export function initModalNewProductionRequest($modal, tables, onOpen) {
    Form
        .create($modal, {clearOnOpen: true})
        .onOpen(onOpen)
        .submitTo(POST, `production_request_new`, {
            tables: tables,
            success: (response) => {
                if(response.needModalConfirmationForGenerateDispatch) {
                    modalConfirmDeleteProductionRequest(response.productionRequestId)
                }
            }
        });
}
