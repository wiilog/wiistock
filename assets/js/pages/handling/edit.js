 import {getStatusHistory} from "@app/pages/handling/common";
 import Routing from '@app/fos-routing';

$(function () {
    const handlingId = Number($(`input[name=handlingId]`).val());

    getStatusHistory(handlingId);

    let $modalDeleteHandling = $('#modalDeleteHandling');
    let $submitDeleteHandling = $('#submitDeleteHandling');
    let urlDeleteHandling = Routing.generate('handling_delete', true);
    InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling);
    initDatePickers();
    $('#submitEditHandling').on('click', function () {
        submitChanges($(this, handlingId));
    });
});

function submitChanges($button, handlingId) {
    const $form = $(`.wii-form`);
    clearFormErrors($form);
    processSubmitAction($form, $button, $button.data(`submit`), {
        success: data => {
            window.location = document.referrer;
        },
    });
}
