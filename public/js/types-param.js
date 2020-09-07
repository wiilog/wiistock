let pathTypes = Routing.generate('types_param_api', true);
let tableTypesConfig = {
    order: [1, 'asc'],
    ajax: {
        "url": pathTypes,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Categorie', 'title': 'Catégorie'},
        {"data": 'Label', 'title': 'Label'},
        {"data": 'Description', 'title': 'Description'},
        {"data": 'sendMail', 'title': 'Envoi de mails au demandeur'},
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
};
let tableTypes = initDataTable('tableTypes', tableTypesConfig);

let modalNewType = $("#modalNewType");
let submitNewType = $("#submitNewType");
let urlNewType = Routing.generate('types_new', true);
InitModal(modalNewType, submitNewType, urlNewType, {tables: [tableTypes]});

let modalEditType = $('#modalEditType');
let submitEditType = $('#submitEditType');
let urlEditType = Routing.generate('types_edit', true);
InitModal(modalEditType, submitEditType, urlEditType, {tables: [tableTypes]});

let ModalDeleteType = $("#modalDeleteType");
let SubmitDeleteType = $("#submitDeleteType");
let urlDeleteType = Routing.generate('types_delete', true)
InitModal(ModalDeleteType, SubmitDeleteType, urlDeleteType, {tables: [tableTypes]});

function typeSelectChange($typeSelect) {
    const $selectedOption = $typeSelect.find('option:selected');
    const $mailCheckContainer = $('.send-mail');
    const $mailCheck = $mailCheckContainer.find('input[name="sendMail"]');
    $mailCheck.prop('checked', false);
    if ($selectedOption.data('needs-send-mail')) {
        $mailCheckContainer.removeClass('d-none');
    } else {
        $mailCheckContainer.addClass('d-none');
    }
}
