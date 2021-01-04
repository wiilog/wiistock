$(document)
    .ready(() => $(".has-tooltip").tooltip()) //existing elements
    .arrive(".has-tooltip", function() {
        $(this).tooltip();
    }); //apply to new elements
