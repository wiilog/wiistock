import {createManagementHeaderPage} from "./utils";

global.addPhoneNumber = addPhoneNumber;
global.deletePhoneNumber = deletePhoneNumber;
global.initTelInput = initTelInput;
global.onTemplateTypeChange = onTemplateTypeChange;

export function initializeAlertTemplate($container) {
    createManagementHeaderPage($container, {
        name: `alertTemplates`,
        newTitle: 'Ajouter un modèle d\'alerte',
        header: {
            route: (template, edit) => Routing.generate('settings_alert_template_header', {template, edit}, true),
            delete: {
                checkRoute: 'settings_alert_template_check_delete',
                selectedEntityLabel: 'alertTemplate',
                route: 'settings_alert_template_delete',
                modalTitle: 'Supprimer le modèle de demande',
            }
        }
    });

    $container.arrive(`input[name=receivers]`, function () {
        initTelInput($(this), $(this).hasClass('edit'));
    });
}

export function initializeNotifications() {

    const $modal = $(`#modalEditNotification`);

    $modal.arrive(`textarea[name=content]`, function() {
        const $content = $(this);
        const $example = $modal.find(`.phone-example .notification`);
        replaceProperExamples($content, $example);
        $content.keyup(function() {
            replaceProperExamples($content, $example);
        });
    });

    const notificationTableConfig = {
        processing: true,
        serverSide: true,
        searching: false,
        paginate: false,
        info: false,
        ajax: {
            url: Routing.generate("notification_template_api", true),
            type: "POST",
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {data: 'type', name: 'type', title: 'Type de la demande/ordre', orderable: false},
            {data: 'content', name: 'content', title: 'Notification', orderable: false},
        ]
    };

    let notificationsTable = initDataTable(`notificationsTable`, notificationTableConfig);

    let $modalModifyEmplacement = $('#modalEditNotification');
    let $submitModifyEmplacement = $('#submitEditNotification');
    let urlModifyEmplacement = Routing.generate('notification_template_edit', true);
    InitModal($modalModifyEmplacement, $submitModifyEmplacement, urlModifyEmplacement, {tables: [notificationsTable]});

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
        initialCountry: "fr",
        autoPlaceholder: "aggressive",
    });
    return $input.data('iti', iti);
}

function onTemplateTypeChange($select) {
    const type = $select.val();
    const $modal = $select.parents('.new-container');
    const path = Routing.generate('alert_template_toggle_template', {type: type});
    $.get(path).then((data) => {
        const $templateContainer = $modal.find('.template-container');
        $templateContainer.empty();
        $templateContainer.append(data);
        $modal.find('.error-msg').empty();
        $modal.find('.is-invalid').removeClass('is-invalid');
        $modal.find('.alert-title').removeClass('d-none');

        if(type === 'mail' || type === 'push') {
            $('#upload-mail-image').on('change', () => updateImagePreview('#preview-mail-image', '#upload-mail-image'));
        } else if(type === 'sms') {
            const $input = $('input[name=receivers]');
            initTelInput($input, true);
        }
    });
}

const replacements = {
    '@numordrelivraison': 'L-123456789',
    '@numordrepreparation': 'P-123456789',
    '@numordrecollecte': 'C-123456789',
    '@numordretransfert': 'T-123456789',
    '@numacheminement': 'A-123456789',
    '@numservice': 'S-123456789',
    '@typelivraison': 'Type-L',
    '@typeacheminement': 'Type-A',
    '@typeservice': 'Type-S',
    '@statut': 'Statut-1',
    '@objet': 'Objet-1',
    '@typecollecte': 'Type-C',
    '@pointdecollecte': 'EMPLACEMENT-C',
    '@origine': 'EMPLACEMENT-O',
    '@destination': 'EMPLACEMENT-D',
    '@demandeur': 'Demandeur-1',
    '@datevalidation': '05/01/2021 14:01:02',
    '@datecreation': '05/01/2021 14:01:02',
    '@empprise': 'EMPLACEMENT-P',
    '@empdepose': 'EMPLACEMENT-D',
    '@dateecheance': '05/01/2021 au 06/01/2021',
    '@numcommande': '123456789',
    '@nbcolis': '5',
    '@chargement': 'Chargement 1',
    '@dechargement': 'Déchargement 1',
    '@dateattendue': '05/01/2021 14:01:02',
    '@nboperations': '50',
}

function replaceProperExamples($content, $example) {
    let content = $content.val();
    content = content.replace(/\n/g, ` <br>`);
    content = content.split(' ').map((word) => replacements[word] || word).join(' ');
    $example.html(content)
}
