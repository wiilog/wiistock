import {getStatusHistory} from "@app/pages/handling/common";
import Form from "@app/form";
import {POST} from "@app/ajax";


$(function () {
    const handlingId = Number($(`input[name=handlingId]`).val());

    getStatusHistory(handlingId);

    let $modalDeleteHandling = $('#modalDeleteHandling');
    Form.create($modalDeleteHandling).submitTo(POST, 'handling_delete', {
        success: response => window.location = response.redirect,
    });

    let $modalEditStatus = $('#modalEditStatut');
    Form.create($modalEditStatus).submitTo(POST, 'handling_status_edit', {
        success: () => { location.reload() }
    });
});

