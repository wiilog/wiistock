$(function() {
    ajaxAutoCompleteFrequencyInit($('.ajax-autocomplete-frequency'));
    let pathCategories = Routing.generate('invParam_api', true);
    let tableCategoriesConfig = {
        ajax: {
            "url": pathCategories,
            "type": "POST"
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Label', 'title': 'Label'},
            {"data": 'Frequence', 'title': 'Fréquence'},
            {"data": 'Permanent', 'title': 'Permanent'},
        ],
        order: [],
        rowConfig: {
            needsRowClickAction: true,
        },
    };

    let tableCategories = initDataTable('tableCategories', tableCategoriesConfig);

    let modalNewCategorie = $("#modalNewCategorie");
    let submitNewCategorie = $("#submitNewCategorie");
    let urlNewCategorie = Routing.generate('categorie_new', true);
    InitModal(modalNewCategorie, submitNewCategorie, urlNewCategorie, {tables: [tableCategories]});

    let modalEditCategory = $('#modalEditCategory');
    let submitEditCategory = $('#submitEditCategory');
    let urlEditCategory = Routing.generate('category_edit', true);
    InitModal(modalEditCategory, submitEditCategory, urlEditCategory, {tables: [tableCategories]});

    let ModalDeleteCategory = $("#modalDeleteCategory");
    let SubmitDeleteCategory = $("#submitDeleteCategory");
    let urlDeleteCategory = Routing.generate('category_delete', true)
    InitModal(ModalDeleteCategory, SubmitDeleteCategory, urlDeleteCategory, {tables: [tableCategories]});
})

function importFile() {
    let path = Routing.generate('update_category', true);
    let importExcel = $('#importExcel')[0];
    let formData = new FormData();
    let files = importExcel.files;
    let fileToSend = files[0];
    let fileName = importExcel.files[0]['name'];
    let extension = fileName.split('.').pop();
    if (extension === "csv") {
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
                if (data.success === true) {
                    ShowBSAlert('Les catégories ont bien été modifiées.', 'success');
                } else if (data.success === false) {
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
                    ShowBSAlert("Le fichier ne s'est pas importé correctement. Veuillez ouvrir le fichier ('log-error.txt') qui vient de se télécharger.", 'danger');
                }
            }
        });
    }
}

let pathFrequencies = Routing.generate('invParamFrequencies_api', true);
let tableFrequenciesConfig = {
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
    rowConfig: {
        needsRowClickAction: true
    },
};

let tableFrequencies = initDataTable('tableFrequencies', tableFrequenciesConfig);

let ModalNewFrequency = $("#modalNewFrequency");
let SubmitNewFrequency = $("#submitNewFrequency");
let urlNewFrequency = Routing.generate('frequency_new', true);
InitModal(ModalNewFrequency, SubmitNewFrequency, urlNewFrequency, {tables: [tableFrequencies]});


let modalEditFrequency = $('#modalEditFrequency');
let submitEditFrequency = $('#submitEditFrequency');
let urlEditFrequency = Routing.generate('frequency_edit', true);
InitModal(modalEditFrequency, submitEditFrequency, urlEditFrequency, {tables: [tableFrequencies]});

let ModalDeleteFrequency = $("#modalDeleteFrequency");
let SubmitDeleteFrequency = $("#submitDeleteFrequency");
let urlDeleteFrequency = Routing.generate('frequency_delete', true)
InitModal(ModalDeleteFrequency, SubmitDeleteFrequency, urlDeleteFrequency, {tables: [tableFrequencies]});

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
