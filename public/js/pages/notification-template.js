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
    '@nbUL': '5',
    '@chargement': 'Chargement 1',
    '@dechargement': 'Déchargement 1',
    '@dateattendue': '05/01/2021 14:01:02',
    '@nboperations': '50',
}


const notificationTableConfig = {
    processing: true,
    serverSide: true,
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
        {data: 'actions', name: 'Actions', title: '', className: 'noVis', orderable: false, width: '10px'},
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

    const $modal = $(`#modalEditNotification`);

    $modal.arrive(`textarea[name=content]`, function() {
        const $content = $(this);
        const $example = $modal.find(`.phone-example .notification`);
        replaceProperExamples($content, $example);
        $content.keyup(function() {
            replaceProperExamples($content, $example);
        });
    });
})

function replaceProperExamples($content, $example) {
    let content = $content.val();
    content = content.replace(/\n/g, ` <br>`);
    content = content.split(' ').map((word) => replacements[word] || word).join(' ');
    $example.html(content)
}


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

        $modalEditAlertTemplate.arrive(`input[name=receivers]`, function () {
            initTelInput($(this));
        });

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

        if(type === 'mail' || type === 'push') {
            $('#upload-mail-image').on('change', () => updateImagePreview('#preview-mail-image', '#upload-mail-image'));
        } else if(type === 'sms') {
            const $input = $('input[name=receivers]');
            initTelInput($input);
        }
    });
}

