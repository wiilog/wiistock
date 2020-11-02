function initImagePopovers() {
    $('[data-toggle="popover-hover"]').popover({
        html: true,
        trigger: 'hover',
        placement: 'bottom',
        content: function () { return '<img src="' + $(this).data('img') + '" />'; }
    });
}
