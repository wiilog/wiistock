const tooltipConfig = {
    html: true,
};

$(document)
    .ready(_ => $(".has-tooltip").tooltip(tooltipConfig)) //existing elements
    .arrive(".has-tooltip", function() {
        $(this).tooltip(tooltipConfig);

    }); //apply to new elements

const popoverConfig = {
    html: true,
};

function createPopover($element) {
    $element.each(function() {
        $(this).popover({
            title: $element.data(`popover-title`),
            content: $element.data(`popover`),
            ...popoverConfig
        });
    });
}

$(document)
    .ready(_ => createPopover($("[data-popover]")))
    .arrive(".has-popover", function() {
        createPopover($(this));
    });
