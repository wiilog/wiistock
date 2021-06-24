($(function () {
    const sensorWrapperTable = initPageDataTable();

    initPageModals([sensorWrapperTable]);
}));

function initPageDataTable() {
    let pathAlertTemplate = Routing.generate('alert_template_api', true);
    let alertTemplateTableConfig = {
        processing: true,
        serverSide: true,
        order: [['name', 'asc']],
        ajax: {
            "url": pathAlertTemplate,
            "type": "POST"
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {data: 'actions', name: 'Actions', title: '', className: 'noVis', orderable: false, width: '10px'},
            {data: 'name', name: 'type', title: 'Nom du modèle', width: '50%'},
            {data: 'type', name: 'type', title: 'Type d\'alerte', width: '50%'},
        ]
    };
    return initDataTable('tableAlertTemplate', alertTemplateTableConfig);
}

function initPageModals(tables) {
    const $modal = $('#modalNewAlertTemplate');

    InitModal($modal, $modal.find('.submit-button'), Routing.generate('alert_template_new', true), {tables});

    let $modalEditAlertTemplate = $("#modalEditAlertTemplate");
    let urlEditAlertTemplate = Routing.generate('alert_template_edit', true);
    InitModal($modalEditAlertTemplate, $modalEditAlertTemplate.find('.submit-button'), urlEditAlertTemplate, {tables});

    let $modalDeleteAlertTemplate = $("#modalDeleteAlertTemplate");
    let urlDeleteAlerteTemplate = Routing.generate('alert_template_delete', true);
    InitModal($modalDeleteAlertTemplate, $modalDeleteAlertTemplate.find('.submit-button'), urlDeleteAlerteTemplate, {tables});
}

function deleteAlertTemplate($button) {
    const alertTemplateId = $button.data('id');
    const $deleteModal = $('#modalDeleteAlertTemplate');

    $deleteModal
        .find('[name="id"]')
        .val(alertTemplateId);

    $deleteModal.modal('show');
}

function onTemplateTypeChange($select) {
    const type = $select.val();
    const $modal = $select.closest('.modal');

    const path = Routing.generate('toggle_template', {type: type});
    $.get(path).then((data) => {
        const $templateContainer = $modal.find('.template-container');
        $templateContainer.empty();

        $templateContainer.append(data);
        $modal.find('.error-msg').empty();
        $modal.find('.is-invalid').removeClass('is-invalid');

        if(type === 'mail') {
            updateImagePreview('#preview-mail-image', '#upload-mail-image');
            initEditor('.editor-container');
        } else if(type === 'sms') {
            const $input = $('input[name=receivers]');
            initTelInput($input);
        }
    });
}

function addPhoneNumber() {
    const $phoneNumberWrapper = $('.phone-number-wrapper');
    const $lastInputContainer = $phoneNumberWrapper.find('.phone-number-container').last();
    const $template = $('.phone-number-template');

    $lastInputContainer.after($template.html());

    const $newInputContainer = $phoneNumberWrapper.find('.phone-number-container').last();
    const $newInput = $newInputContainer.find('input[name=receivers]');

    initTelInput($newInput);
}

function deletePhoneNumber($button) {
    const $phoneNumberContainer = $button.closest('.phone-number-container');

    $phoneNumberContainer.remove();
}

function initTelInput($input) {
    const iti = intlTelInput($input[0], {
        utilsScript: '/build/vendor/intl-tel-input/utils.js',
        preferredCountries: ['fr'],
        initialCountry: 'fr'
    });
    return $input.data('iti', iti);
}

function openVariablesDictionary() {
    const $variablesModal = $('#variablesModal');

    $variablesModal.modal('show');
}
