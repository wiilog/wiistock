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



function displayErrorCategorie(data) {
    let modal = $("#modalNewCategorie");
    let msg = 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
}