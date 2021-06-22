const notificationTableConfig = {
    processing: true,
    serverSide: true,
    order: [['type', 'asc']],
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

const alertTableConfig = {
    processing: true,
    serverSide: true,
    order: [['name', 'asc']],
    ajax: {
        url: Routing.generate('alert_template_api', true),
        type: "POST"
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {data: 'actions', name: 'Actions', title: '', className: 'noVis', orderable: false},
        {data: 'name', name: 'type', title: 'Nom du modèle'},
        {data: 'type', name: 'type', title: 'Type d\'alerte'},
    ]
};

const TAB_NOTIFICATIONS = 1;
const TAB_ALERTS = 2;

const HASH_NOTIFICATIONS = `#notifications`;
const HASH_ALERTS = `#alertes`;

let selectedTab = TAB_NOTIFICATIONS;
let notificationsTable;
let alertsTable;

$(document).ready(() => {
    switchPageBasedOnHash();
    $(window).on("hashchange", switchPageBasedOnHash);
})


function switchPageBasedOnHash() {
    let hash = window.location.hash;
    if (hash === HASH_NOTIFICATIONS) {
        switchNotifications();
    } else if(hash === HASH_ALERTS) {
        switchAlerts();
    } else {
        switchNotifications();
        window.location.hash = HASH_NOTIFICATIONS;
    }

    $(`.notification-tabs a`).removeClass(`active`);
    $(`.notification-tabs a[href="${hash}"]`).addClass(`active`);
}

function switchNotifications() {
    selectedTab = TAB_NOTIFICATIONS;
    window.location.hash = HASH_NOTIFICATIONS;

    if(!notificationsTable) {
        notificationsTable = initDataTable(`notificationsTable`, notificationTableConfig);

        let $modalModifyEmplacement = $('#modalEditNotification');
        let $submitModifyEmplacement = $('#submitEditNotification');
        let urlModifyEmplacement = Routing.generate('notification_template_edit', true);
        InitModal($modalModifyEmplacement, $submitModifyEmplacement, urlModifyEmplacement, {tables: [notificationsTable]});
    } else {
        notificationsTable.ajax.reload();
    }

    $(`.alertsTableContainer, [data-target="#modalNewAlertTemplate"]`).addClass('d-none');
    $(`.notificationsTableContainer`).removeClass('d-none');
    $(`.action-button`).removeClass('d-none');
    $(`#notificationsTable_filter`).parent().show();
    $(`#alertsTable_filter`).parent().hide();
}

function switchAlerts() {
    selectedTab = TAB_ALERTS;
    window.location.hash = HASH_ALERTS;

    if(!alertsTable) {
        alertsTable = initDataTable(`alertsTable`, alertTableConfig);

        const $modal = $('#modalNewAlertTemplate');

        InitModal($modal, $modal.find('.submit-button'), Routing.generate('alert_template_new', true), {tables: [alertsTable]});

        let $modalEditAlertTemplate = $("#modalEditAlertTemplate");
        let urlEditAlertTemplate = Routing.generate('alert_template_edit', true);
        InitModal($modalEditAlertTemplate, $modalEditAlertTemplate.find('.submit-button'), urlEditAlertTemplate, {tables: [alertsTable]});

        let $modalDeleteAlertTemplate = $("#modalDeleteAlertTemplate");
        let urlDeleteAlerteTemplate = Routing.generate('alert_template_delete', true);
        InitModal($modalDeleteAlertTemplate, $modalDeleteAlertTemplate.find('.submit-button'), urlDeleteAlerteTemplate, {tables: [alertsTable]});
    } else {
        alertsTable.ajax.reload();
    }

    $(`.action-button`).addClass('d-none');
    $(`.alertsTableContainer, [data-target="#modalNewAlertTemplate"]`).removeClass('d-none');
    $(`.notificationsTableContainer`).addClass('d-none');
    $(`#notificationsTable_filter`).parent().hide();
    $(`#alertsTable_filter`).parent().show();
}

const CURSOR_BEGIN_PLACEHOLDER = `<i data-from></i>`;
const CURSOR_END_PLACEHOLDER = `<i data-to></i>`;

function onEditModalLoad($modal, editor) {
    // pour la coloration des variables mais ça marche pas c'est pas prioritaire donc en pause pour l'instant
    //
    // editor.on(`text-change`, function() {
    //     let content = editor.root.innerHTML;
    //     const selec = JSON.parse(JSON.stringify(editor.getSelection()));
    //
    //     const $content = $(`<div>`, {
    //         html: $.parseHTML(content.replace(/(@[a-z0-9]+)/gi, `<span class="highlighted-variable">$1</span>`))
    //     });
    //
    //     $content.find(`.highlighted-variable`).each(function() {
    //         $(this).unwrap(`.highlighted-variable`);
    //     });
    //
    //     editor.root.innerHTML = $content.html();
    //     editor.setSelection(selec);
    // })
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

    const path = Routing.generate('alert_template_toggle_template', {type: type});
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
