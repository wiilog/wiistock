let pathFieldsParam = Routing.generate('fields_param_api', true);
let tableFields = $('#tableFieldsParam').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathFieldsParam,
        "type": "POST"
    },
    columns:[
        { "data": 'Actions', 'title' : 'Actions' },
        { "data": 'entityCode', 'title' : 'Catégorie' },
        { "data": 'fieldCode', 'title' : 'Champ fixe' },
    ],
    order: [[1, "desc"]],
    columnDefs: [
        {orderable:false, targets:0}
    ]
});

let modalEditFields = $('#modalEditFields');
let submitEditFields = $('#submitEditFields');
let urlEditFields = Routing.generate('fields_edit', true);
InitialiserModal(modalEditFields, submitEditFields, urlEditFields, tableFields, displayErrorFields);

function displayErrorFields(data) {
    let modal = $("#modalEditFields");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}
