import {POST} from "@app/ajax";
import Form from "@app/form";
import Camera from "@app/camera";
import Modal from "@app/modal";

export function displayAttachmentRequired($select) {
    const $modal = $select.closest(`.modal`);
    const statusData = $select.select2(`data`)[0];
    const requiredAttachment = statusData && statusData.requiredAttachment ? 1 : 0;

    $modal.find(`[name=isFileNeeded]`).val(requiredAttachment);
    $modal.find(`[name=isSheetFileNeeded]`).val(requiredAttachment);
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
            success: () => {
                successCallback()
            }
        });

    $modalUpdateProductionRequestStatus.modal(`show`);
}
