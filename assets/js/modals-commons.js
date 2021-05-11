let filled = true;

$(function () {
    const $filled = $('#filled');
    const $edit = $('.edit-button');

    filled = $filled.length > 0 && $edit.length > 0 ? $filled.val() : true;

    if (!filled) {
        $edit.click();
    }
})
