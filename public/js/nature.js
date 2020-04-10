let pathNature = Routing.generate('nature_param_api', true);
let tableNature = $('#tableNatures').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathNature,
        "type": "POST"
    },
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
    ],
    order: [1, 'asc'],
    columns:[
        { "data": 'Actions', 'title' : '', className: 'noVis' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Code', 'title' : 'Code' },
        { "data": 'Quantité par défaut', 'title' : 'Quantité par défaut' },
        { "data": 'Préfixe', 'title' : 'Préfixe' },
        { "data": 'Couleur', 'title' : 'Couleur' },
    ],
    rowCallback: function (row, data) {
        initActionOnRow(row);
    },
});

let modalNewNature = $("#modalNewNature");
let submitNewNature = $("#submitNewNature");
let urlNewNature = Routing.generate('nature_new', true);
InitialiserModal(modalNewNature, submitNewNature, urlNewNature, tableNature, displayErrorNature);

let modalEditNature = $('#modalEditNature');
let submitEditNature = $('#submitEditNature');
let urlEditNature = Routing.generate('nature_edit', true);
InitialiserModal(modalEditNature, submitEditNature, urlEditNature, tableNature, displayErrorNature);

let modalDeleteNature = $("#modalDeleteNature");
let submitDeleteNature = $("#submitDeleteNature");
let urlDeleteNature = Routing.generate('nature_delete', true)
InitialiserModal(modalDeleteNature, submitDeleteNature, urlDeleteNature, tableNature);

function displayErrorNature(data) {
    let modal = $("#modalNewNature");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}
