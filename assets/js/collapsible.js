$('body').on('click', '.toggle-collapsible', function() {
    $(this)
        .toggleClass('expanded')
        .next()
        .toggleClass('expanded');
})
