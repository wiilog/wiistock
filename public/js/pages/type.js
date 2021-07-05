let pathTypes = Routing.generate('types_param_api', true);
let tableTypesConfig = {
    order: [['Categorie', 'asc']],
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
        {"data": 'notifications', 'title': 'Notifications'},
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

modalNewType.on('hidden.bs.modal', function () {
    clearModal(modalNewType);
    clearFormErrors(modalNewType);

    modalNewType.find('.needs-default-locations').addClass(`d-none`);
    modalNewType.find('.send-mail').addClass(`d-none`);
    modalNewType.find('.notifications-emergencies').addClass(`d-none`);
    modalNewType.find('.notifications-emergencies-select').addClass(`d-none`);
    modalNewType.find('.enable-notifications').addClass(`d-none`);
});

let modalEditType = $('#modalEditType');
let submitEditType = $('#submitEditType');
let urlEditType = Routing.generate('types_edit', true);
InitModal(modalEditType, submitEditType, urlEditType, {tables: [tableTypes]});

let ModalDeleteType = $("#modalDeleteType");
let SubmitDeleteType = $("#submitDeleteType");
let urlDeleteType = Routing.generate('types_delete', true)
InitModal(ModalDeleteType, SubmitDeleteType, urlDeleteType, {tables: [tableTypes]});

function onNotificationsChange($checkbox) {
    const $modal = $checkbox.closest(`.modal`);
    const checked = $checkbox.is(':checked');

    if (checked) {
        $modal
            .find('[name="emergenciesNotificationEnabled"]')
            .prop('checked', false)
            .trigger('change');
    }
}

function onNotificationsEmergenciesChange($checkbox) {
    const $modal = $checkbox.closest(`.modal`);
    const $container = $modal.find(`.notifications-emergencies-select`);
    const $select = $modal.find(`[name="notificationsEmergencies"]`);
    const isChecked = $checkbox.is(':checked');

    if (isChecked) {
        $modal
            .find('[name="notificationsEnabled"]')
            .prop('checked', false)
    }

    if (isChecked) {
        $container.removeClass(`d-none`);
        $select.addClass(`needed`);
    }
    else {
        $container.addClass(`d-none`);
        $select.removeClass(`needed`);
    }

    $select
        .val(null)
        .trigger(`change`);
}

function typeSelectChange($typeSelect, $modal) {
    const $selectedOption = $typeSelect.find('option:selected');
    const $mailCheckContainer = $modal.find('.send-mail');
    const $defaultLocations = $modal.find('.needs-default-locations');
    const $notificationsContainer = $modal.find('.enable-notifications');
    const $mailCheck = $mailCheckContainer.find('input[name="sendMail"]');
    const $notificationsEmergencies = $modal.find('select[name=notificationsEmergencies]');

    const category = $selectedOption.data(`category`);
    $notificationsEmergencies.val(null).trigger(`change`);
    $notificationsEmergencies.find(`option`).prop(`disabled`, true)
    $notificationsEmergencies.find(`option[data-category="${category}"]`).prop(`disabled`, false);

    $mailCheck.prop('checked', false);

    if ($selectedOption.data('needs-send-mail')) {
        $mailCheckContainer.removeClass('d-none');
    } else {
        $mailCheckContainer.addClass('d-none');
    }

    if ($selectedOption.data('enable-notifications')) {
        $notificationsContainer.removeClass('d-none');
    } else {
        $notificationsContainer.addClass('d-none');
    }

    if ($selectedOption.data('needs-default-locations')) {
        Select2Old.location($defaultLocations.find('.ajax-autocomplete-location'))
        $defaultLocations.removeClass('d-none');
    } else {
        $defaultLocations.addClass('d-none');
    }
}

function initTypeLocations($modal) {
    const $defaultLocations = $modal.find('.needs-default-locations');
    Select2Old.location($defaultLocations.find('.ajax-autocomplete-location'));
}
