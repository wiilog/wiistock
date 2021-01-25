let pathTypes = Routing.generate('types_param_api', true);
let tableTypesConfig = {
    order: ['Categorie', 'asc'],
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

function typeSelectChange($typeSelect, $modal) {
    const $selectedOption = $typeSelect.find('option:selected');
    const $mailCheckContainer = $('.send-mail');
    const $defaultLocations = $modal.find('.needs-default-locations');
    const $mailCheck = $mailCheckContainer.find('input[name="sendMail"]');

    $mailCheck.prop('checked', false);

    if ($selectedOption.data('needs-send-mail')) {
        $mailCheckContainer.removeClass('d-none');
    } else {
        $mailCheckContainer.addClass('d-none');
    }
    if ($selectedOption.data('needs-default-locations')) {
        Select2.location($defaultLocations.find('.ajax-autocomplete-location'))
        $defaultLocations.removeClass('d-none');
    } else {
        $defaultLocations.addClass('d-none');
    }
}

function initTypeLocations($modal) {
    const $defaultLocations = $modal.find('.needs-default-locations');
    Select2.location($defaultLocations.find('.ajax-autocomplete-location'));
}
