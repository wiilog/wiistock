$(function() {
    $('.table').each(function() {
        $(this).DataTable({
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax:{
                "url": Routing.generate('fields_param_api', {entityCode: $(this).parent().attr('id')}),
                "type": "POST"
            },
            columns:[
                { "data": 'Actions', 'title' : 'Actions' },
                { "data": 'entityCode', 'title' : 'Catégorie' },
                { "data": 'fieldCode', 'title' : 'Champ fixe' },
            ],
            order: [[1, "desc"]],
            "pageLength": 5,
            "lengthMenu": [ 5, 10 ],
            columnDefs: [
                {orderable:false, targets:0}
            ]
        })
    });
});

let modalEditFields = $('#modalEditFields');
let submitEditFields = $('#submitEditFields');
let urlEditFields = Routing.generate('fields_edit', true);
InitialiserModal(modalEditFields, submitEditFields, urlEditFields, null, displayErrorFields);

function displayErrorFields(data) {
    let modal = $("#modalEditFields");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}

function switchDisplay(checkbox) {
    if (!checkbox.is(':checked')) {
        $('.checkbox').prop('checked', false);
    }
}

function switchDisplayByMust(checkbox) {
    if (checkbox.is(':checked')) {
        $('.checkbox[name="displayed"]').prop('checked', true);
    }
}
