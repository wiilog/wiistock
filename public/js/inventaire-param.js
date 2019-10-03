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
InitialiserModal(ModalDeleteFrequency, SubmitDeleteFrequency, urlDeleteFrequency, null, displayErrorFrequency, false);

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

function displayErrorFrequency(data) {
    let modal = $("#modalNewFrequency");
    let msg = 'Ce label de fréquence existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
}

function importFile() {
    let path = Routing.generate('update_category', true);
    let formData = new FormData();
    let files = $('#importExcel')[0].files;
    let fileToSend = files[0];
    let fileName = $('#importExcel')[0].files[0]['name'];
    let extension = fileName.split('.').pop();
    if (extension == "csv")
    {
        formData.append('file', fileToSend);
        $.ajax({
            url: path,
            data: formData,
            type: "post",
            contentType: false,
            processData: false,
            cache: false,
            dataType: "json",
            success: function (data) {
                if (data.success == true)
                {
                    alertSuccessMsg(data);
                }
                else if (data.success == false)
                {
                    let exportedFilenmae = 'log-error.txt';
                    let pathFile = '../uploads/log/';
                    let pathWithFileName = pathFile.concat(data.nameFile);
                    let link = document.createElement("a");
                    console.log(link);
                    link.setAttribute("href", pathWithFileName);
                    link.setAttribute("download", exportedFilenmae);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }
        });
    }
}