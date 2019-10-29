let pathTypes = Routing.generate('typesParam_api', true);
let tableTypes = $('#tableTypes').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathTypes,
        "type": "POST"
    },
    columns:[
        { "data": 'Label', 'title' : 'Label' },
        { "data": 'Categorie', 'title' : 'Catégorie' },
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
    let msg = null;
    if (data === false) {
        msg = 'Ce label de type pour cette catégorie existe déjà. Veuillez en choisir un autre.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'Le type a bien été créé.';
        alertSuccessMsg(msg);
    }
}

function displayErrorTypeEdit(data) {
    let modal = $("#modalEditType");
    let msg = null;
    if (data === false) {
        msg = 'Ce label de type pour cette catégorie existe déjà. Veuillez en choisir un autre.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'Le type a bien été modifié';
        alertSuccessMsg(msg);
    }
}