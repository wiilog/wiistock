export function displayAttachmentRequired($select) {
    const $modal = $select.closest(`.modal`);
    const statusData = $select.select2(`data`)[0];
    const requiredAttachment = statusData && statusData.requiredAttachment ? 1 : 0;

    $modal.find(`[name=isFileNeeded]`).val(requiredAttachment);
    $modal.find(`[name=isSheetFileNeeded]`).val(requiredAttachment);
}
