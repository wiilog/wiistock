let pathCategories = Routing.generate('invParam_api', true);
let tableCategories = $('#tableCategories').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathCategories,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis'},
        {"data": 'Label', 'title': 'Label'},
        {"data": 'Frequence', 'title': 'Fréquence'},
        {"data": 'Permanent', 'title': 'Permanent'},
    ],
    rowCallback: function (row, data) {
        initActionOnRow(row);
    }
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


function displayErrorCategorie(data) {
    let modal = $("#modalNewCategorie");
    let msg = null;
    if (data === false) {
        msg = 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'La catégorie a bien été créée.'
        alertSuccessMsg(msg);
    }
}

function displayErrorCategorieEdit(data) {
    let modal = $("#modalEditCategory");
    let msg = null;
    if (data === false) {
        msg = 'Ce label de catégorie existe déjà. Veuillez en choisir un autre.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'La catégorie a bien été modifiée';
        alertSuccessMsg(msg);
    }
}

function importFile() {
    let path = Routing.generate('update_category', true);
    let formData = new FormData();
    let files = $('#importExcel')[0].files;
    let fileToSend = files[0];
    let fileName = $('#importExcel')[0].files[0]['name'];
    let extension = fileName.split('.').pop();
    if (extension == "csv") {
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
                if (data.success == true) {
                    alertSuccessMsg("Les catégories ont bien été modifiées.");
                } else if (data.success == false) {
                    let exportedFilenmae = 'log-error.txt';
                    let pathFile = '../uploads/log/';
                    let pathWithFileName = pathFile.concat(data.nameFile);
                    let link = document.createElement("a");
                    link.setAttribute("href", pathWithFileName);
                    link.setAttribute("download", exportedFilenmae);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    alertErrorMsg("Le fichier ne s'est pas importé correctement. Veuillez ouvrir le fichier ('log-error.txt') qui vient de se télécharger.");
                }
            }
        });
    }
}

let pathFrequencies = Routing.generate('invParamFrequencies_api', true);
let tableFrequencies = $('#tableFrequencies').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    searching: false,
    info: false,
    ajax: {
        "url": pathFrequencies,
        "type": "POST"
    },
    order: [2, 'asc'],
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Label', 'title': 'Label'},
        {"data": 'NbMonths', 'title': 'Nombre de mois'},
    ],
    rowCallback: function (row, data) {
        initActionOnRow(row);
    }
});

let ModalNewFrequency = $("#modalNewFrequency");
let SubmitNewFrequency = $("#submitNewFrequency");
let urlNewFrequency = Routing.generate('frequency_new', true);
InitialiserModal(ModalNewFrequency, SubmitNewFrequency, urlNewFrequency, tableFrequencies, displayErrorFrequencyAndUpdateList, false);


let modalEditFrequency = $('#modalEditFrequency');
let submitEditFrequency = $('#submitEditFrequency');
let urlEditFrequency = Routing.generate('frequency_edit', true);
InitialiserModal(modalEditFrequency, submitEditFrequency, urlEditFrequency, tableFrequencies, displayErrorFrequencyEdit, false, false);

let ModalDeleteFrequency = $("#modalDeleteFrequency");
let SubmitDeleteFrequency = $("#submitDeleteFrequency");
let urlDeleteFrequency = Routing.generate('frequency_delete', true)
InitialiserModal(ModalDeleteFrequency, SubmitDeleteFrequency, urlDeleteFrequency, tableFrequencies, openModalShow);

function displayErrorFrequencyAndUpdateList(data) {
    let modal = $("#modalNewFrequency");
    let msg = null;
    if (data === false) {
        msg = 'Ce label de fréquence existe déjà. Veuillez en choisir un autre.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'La fréquence a bien été créée.'
        alertSuccessMsg(msg);
    }
}

function displayErrorFrequencyEdit(data) {
    let modal = $("#modalEditFrequency");
    let msg = null;
    if (data === false) {
        msg = 'Ce label de fréquence existe déjà. Veuillez en choisir un autre.';
        displayError(modal, msg, data);
    } else {
        modal.find('.close').click();
        msg = 'La fréquence a bien été modifiée.';
        alertSuccessMsg(msg);
    }
}

function openModalShow(data) {
    if (data) {
        $('#showFrequencies').click();
    }
}


function downloadModele() {
    const pathFile = '../uploads/modele/';
    const pathWithFileName = pathFile.concat('modeleImportCategorie.csv');
    const $link = $('<a/>', {
        href: pathWithFileName,
        download: 'modeleImportCategorie.csv',
        hidden: true
    });

    $('body').append($link);
    $link.on('click', function (e) {
        e.preventDefault();  //stop the browser from following
        window.location.href = $link.attr('href');
    });

    $link.trigger('click');
    $link.remove();
}
