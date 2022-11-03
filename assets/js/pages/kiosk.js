import '@styles/pages/kiosk.scss';

$(function() {
    let modal = $("#modalPrintHistory");
    let submit = $("#cancel");
    InitModal(modal, submit, '', {});

    $('#openModalPrintHistory').on('click', function() {
        modal.modal('show');
    });
});
