let pathTypes = Routing.generate('types_param_api', true);
let tableTypes = $('#tableTypes').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathTypes,
        "type": "POST"
    },
    columns:[
        { "data": 'Categorie', 'title' : 'Catégorie' },
        { "data": 'Label', 'title' : 'Label' },
        { "data": 'Description', 'title' : 'Description' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
});

let modalNewType = $("#modalNewType");
let submitNewType = $("#submitNewType");
let urlNewType = Routing.generate('types_new', true);
InitialiserModal(modalNewType, submitNewType, urlNewType, tableTypes, displayErrorType, false);

let modalEditType = $('#modalEditType');
let submitEditType = $('#submitEditType');
let urlEditType = Routing.generate('types_edit', true);
InitialiserModal(modalEditType, submitEditType, urlEditType, tableTypes, displayErrorTypeEdit, false);

let ModalDeleteType = $("#modalDeleteType");
let SubmitDeleteType = $("#submitDeleteType");
let urlDeleteType = Routing.generate('types_delete', true)
InitialiserModal(ModalDeleteType, SubmitDeleteType, urlDeleteType, tableTypes);

function displayErrorType(data) {
    let modal = $("#modalNewType");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}

function displayErrorTypeEdit(data) {
    let modal = $("#modalEditType");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}