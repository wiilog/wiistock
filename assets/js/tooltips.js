$(document)
    .ready(() => $(".has-tooltip").tooltip()) //existing elements
    .arrive(".has-tooltip", () => $(this).tooltip()); //apply to new elements

