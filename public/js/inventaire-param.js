let pathCategories = Routing.generate('invParam_api', true);
let tableCategories = $('#tableCategories').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathCategories,
        "type": "POST"
    },
    columns:[
        { "data": 'Label', 'title' : 'Label' },
        { "data": 'Frequence', 'title' : 'Fréquence' },
        { "data": 'Permanent', 'title' : 'Permanent' },
        { "data": 'Actions', 'title' : 'Actions' }
    ],
});

let modalNewCategorie = $("#modalNewCategorie");
let submitNewCategorie = $("#submitNewCategorie");
let urlNewCategorie = Routing.generate('categorie_new', true);
InitialiserModal(modalNewCategorie, submitNewCategorie, urlNewCategorie, tableCategories, displayErrorCategorie, false);

let modalEditCategory = $('#modalEditCategory');
let submitEditCategory = $('#submitEditCategory');
let urlEditCategory = Routing.generate('category_edit', true);
InitialiserModal(modalEditCategory, submitEditCategory, urlEditCategory, tableCategories, displayErrorCategorieEdit, false, false);

let ModalDeleteCategory = $("#modalDeleteCategory");
let SubmitDeleteCategory = $("#submitDeleteCategory");
let urlDeleteCategory = Routing.generate('category_delete', true)
InitialiserModal(ModalDeleteCategory, SubmitDeleteCategory, urlDeleteCategory, tableCategories);

let ModalDeleteFrequency = $("#modalNewFrequency");
let SubmitDeleteFrequency = $("#submitNewFrequency");
let urlDeleteFrequency = Routing.generate('frequency_new', true)
InitialiserModal(ModalDeleteFrequency, SubmitDeleteFrequency, urlDeleteFrequency, null, displayErrorFrequencyAndUpdateList, false);

function displayErrorCategorie(data) {
    let modal = $("#modalNewCategorie");
    let msg = 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
}

function displayErrorCategorieEdit(data) {
    let modal = $("#modalEditCategory");
    let msg = 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
}

function displayErrorFrequencyAndUpdateList(data) {
    let modal = $("#modalNewFrequency");
    let msg = 'Ce label de fréquence existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
    updateListFrequencies();
}

function updateListFrequencies() {
    $.post(Routing.generate('frequency_select', true), function(data) {
       $('#frequencies').html(data);
    });
}