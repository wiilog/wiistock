function initImagePopovers() {
    $(`body`).popover({
        selector: `[data-toggle=popover-hover]`,
        html: true,
        trigger: `hover`,
        placement: `bottom`,
        content: function () {
            return `<img alt src="${$(this).data('img')}" width="400" />`;
        }
    });
}
