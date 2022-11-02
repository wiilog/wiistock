import '@styles/pages/kiosk/general.scss';

$(document).ready(function() {
    console.log('Kiosk General Page Ready');

    let modal = $("#modalDeTest");
    let submit = $("#submit");
    let url = '#';
    InitModal(modal, submit, url, {});

    $('#openModal').on('click', function() {
        modal.modal('show');
    });

});
