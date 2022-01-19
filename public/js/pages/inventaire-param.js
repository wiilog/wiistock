$(function() {
    Select2Old.frequency($('.ajax-autocomplete-frequency'));
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

let pathFrequencies = Routing.generate('invParamFrequencies_api', true);
let tableFrequenciesConfig = {
    searching: false,
    info: false,
    ajax: {
        "url": pathFrequencies,
        "type": "POST"
    },
    order: [['NbMonths', 'asc']],
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
    const url = window.location.protocol+'//'+window.location.host+'/modele/modeleImportCategorie.csv';
    const $link = $('<a/>', {
        href: url,
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
