let filled = true;

$(function () {
    const $filled = $(`#filled`);
    const $edit = $(`.edit-button`);

    filled = $filled.exists() && $edit.exists() ? $filled.val() : true;
    if (!filled) {
        $edit.click();
    }
});
