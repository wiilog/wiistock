import {getStatusHistory} from "@app/pages/handling/common";


$(function () {
    const handlingId = Number($(`input[name=handlingId]`).val());

    getStatusHistory(handlingId);

    let $modalDeleteHandling = $('#modalDeleteHandling');
    let $submitDeleteHandling = $('#submitDeleteHandling');
    let urlDeleteHandling = Routing.generate('handling_delete', true);
    InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling);

    let $modalEditStatus = $('#modalEditStatut');
    let $submitEditStatut = $('#submitEditStatut');
    let urlEditStatut = Routing.generate('handling_status_edit', true);
    InitModal($modalEditStatus, $submitEditStatut, urlEditStatut, {success: () => { location.reload() }});
});

