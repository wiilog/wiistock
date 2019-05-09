$('.select2').select2();

let pathEmplacement = Routing.generate("emplacement_api", true);
let tableEmplacement = $('#tableEmplacement_id').DataTable({
    processing: true,
    serverSide: true,
    
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathEmplacement,
        "type": "POST"
    },
    columns: [
        { "data": 'Nom' },
        { "data": 'Description' },
        { "data": 'Actions' }
    ],
    buttons: [
        'copy', 'excel', 'pdf'
    ]
});

let modalNewEmplacement = $("#modalNewEmplacement");
let submitNewEmplacement = $("#submitNewEmplacement");
let urlNewEmplacement = Routing.generate('emplacement_new', true);
InitialiserModal(modalNewEmplacement, submitNewEmplacement, urlNewEmplacement, tableEmplacement);

let modalDeleteEmplacement = $('#modalDeleteEmplacement');
let submitDeleteEmplacement = $('#submitDeleteEmplacement');
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true);
InitialiserModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, tableEmplacement);

let modalModifyEmplacement = $('#modalEditEmplacement');
let submitModifyEmplacement = $('#submitEditEmplacement');
let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
InitialiserModal(modalModifyEmplacement, submitModifyEmplacement, urlModifyEmplacement, tableEmplacement);

function checkAndDeleteRow(icon) {
    let modalBody = modalDeleteEmplacement.find('.modal-body');
    let id = icon.data('id');
    let param = JSON.stringify(id);

    $.post(Routing.generate('emplacement_check_delete'), param, function(resp) {
        modalBody.html(resp.html);
        if (resp.delete == false) {
            submitDeleteEmplacement.hide();
        } else {
            submitDeleteEmplacement.show();
            submitDeleteEmplacement.attr('value', id);
        }
    });
}