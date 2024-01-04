import {POST} from "@app/ajax";

global.onProductionRequestTypeChange = onProductionRequestTypeChange;
global.displayAttachmentRequired = displayAttachmentRequired;

$(function () {
    initProductionRequestModal($(`#modalNewProductionRequest`), `production_request_new`);
});

function onProductionRequestTypeChange($select){
    const $modal = $select.closest(`.modal`);
    const $typeSelect = $modal.find(`[name=type]`);
    const $selectStatus = $modal.find(`[name=status]`);

    $selectStatus.prop(`disabled`, !Boolean($typeSelect.val()))
    $selectStatus.val(null).trigger(`change`);
}

function displayAttachmentRequired($select) {
    const $modal = $select.closest(`.modal`);
    const statusData = $select.select2(`data`)[0];

    const requiredAttachment = statusData && statusData.requiredAttachment ? 1 : 0;
    $modal.find(`[name=isFileNeeded]`).val(requiredAttachment);
    $modal.find(`[name=isSheetFileNeeded]`).val(requiredAttachment);
}


function initProductionRequestModal($modal, submitRoute) {
    Form
        .create($modal, {clearOnOpen: true})
        .submitTo(POST, submitRoute, {
            success: (data) => {
                if(data.success) {
                    console.log(`REFRESH liste`);
                }
            }});
}
