import {createManagementPage} from "./utils";

global.addPhoneNumber = addPhoneNumber;
global.deletePhoneNumber = addPhoneNumber;
global.initTelInput = addPhoneNumber;
global.onTemplateTypeChange = onTemplateTypeChange;

export function initializeAlertTemplate($container, canEdit) {
    createManagementPage($container, {
        name: `alertTemplates`,
        edit: canEdit,
        newTitle: 'Ajouter un modèle d\'alerte',
        header: {
            route: (template, edit) => Routing.generate('settings_alert_template_header', {template, edit}, true),
            delete: {
                checkRoute: 'settings_alert_template_check_delete',
                selectedEntityLabel: 'alertTemplate',
                route: 'settings_alert_template_delete',
                modalTitle: 'Supprimer le modèle de demande',
            },
        },
        table: {
            route: () => Routing.generate('settings_alert_template_api', true),
            hidden: true,
            columns: [
                {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            ],
            form: {},
        },
    });
    $container.arrive(`input[name=receivers]`, function () {
        initTelInput($(this), $(this).hasClass('edit'));
    });
}

function addPhoneNumber() {
    const $phoneNumberWrapper = $('.phone-number-wrapper');
    const $lastInputContainer = $phoneNumberWrapper.find('.phone-number-container').last();
    const $template = $('.phone-number-template');

    $lastInputContainer.after($template.html());

    const $newInputContainer = $phoneNumberWrapper.find('.phone-number-container').last();
    const $newInput = $newInputContainer.find('input[name=receivers]');

    initTelInput($newInput, true);
}

function deletePhoneNumber($button) {
    const $phoneNumberContainer = $button.closest('.phone-number-container');

    $phoneNumberContainer.remove();
}

function initTelInput($input, edit) {
    const iti = intlTelInput($input[0], {
        utilsScript: '/build/vendor/intl-tel-input/utils.js',
        preferredCountries: ['fr'],
        initialCountry: 'fr',
        allowDropdown: edit
    });
    return $input.data('iti', iti);
}

function onTemplateTypeChange($select) {
    const type = $select.val();
    const $modal = $select.closest('.modal');

    const path = Routing.generate('alert_template_toggle_template', {type: type});
    $.get(path).then((data) => {
        const $templateContainer = $modal.find('.template-container');
        $templateContainer.empty();

        $templateContainer.append(data);
        $modal.find('.error-msg').empty();
        $modal.find('.is-invalid').removeClass('is-invalid');

        if(type === 'mail' || type === 'push') {
            $('#upload-mail-image').on('change', () => updateImagePreview('#preview-mail-image', '#upload-mail-image'));
        } else if(type === 'sms') {
            const $input = $('input[name=receivers]');
            initTelInput($input, true);
        }
    });
}
