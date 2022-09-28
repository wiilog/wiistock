import EditableDatatable, {MODE_CLICK_EDIT, MODE_NO_EDIT, SAVE_MANUALLY, STATE_VIEWING} from "../../editatable";

const MODE_ARRIVAL_DISPUTE = 'arrival-dispute';
const MODE_RECEPTION_DISPUTE = 'reception-dispute';
const MODE_PURCHASE_REQUEST = 'purchase-request';
const MODE_ARRIVAL = 'arrival';
const MODE_DISPATCH = 'dispatch';
const MODE_HANDLING = 'handling';

const DISABLED_LABELS_TRANSLATION_PAGES = [
    `#reception-dispute-statuses-table`,
    `#purchase-request-statuses-table`
];

const $managementButtons = $(`.save-settings, .discard-settings`);
let $canTranslate = true;

export function initializeArrivalDisputeStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, MODE_ARRIVAL_DISPUTE);
}

export function initializeReceptionDisputeStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, MODE_RECEPTION_DISPUTE);
}

export function initializePurchaseRequestStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, MODE_PURCHASE_REQUEST);
}

export function initializeDispatchStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_DISPATCH)
}

export function initializeArrivalStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_ARRIVAL)
}

export function initializeHandlingStatuses($container, canEdit) {
    initializeStatusesByTypes($container, canEdit, MODE_HANDLING)
}

function initializeStatuses($container, canEdit, mode, categoryType) {
    const $addButton = $container.find(`.add-row-button`);
    const $translateButton = $container.find(`.translate-labels-button`);
    const $filtersContainer = $container.find('.filters-container');
    const $pageBody = $container.find('.page-body');
    const $addRow = $container.find(`.add-row`);
    const $translateLabels = $container.find('.translate-labels');
    const $statusStateOptions = $container.find('[name=status-state-options]');
    const statusStateOptions = JSON.parse($statusStateOptions.val());
    const tableSelector = `#${mode}-statuses-table`;
    const type = $('[name=type]:checked').val();
    const $modalEditTranslations = $container.find(".edit-translation-modal");
    const route = Routing.generate(`settings_statuses_api`, {mode, type});

    const table = EditableDatatable.create(tableSelector, {
        route,
        deleteRoute: `settings_delete_status`,
        mode: canEdit ? MODE_CLICK_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: true,
        ordering: true,
        paging: true,
        scrollY: false,
        scrollX: false,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
            if(!DISABLED_LABELS_TRANSLATION_PAGES.includes(tableSelector)) {
                $addRow.addClass('d-none');
                if ($canTranslate) {
                    $translateLabels.removeClass('d-none');
                }

                $translateButton
                    .off('click')
                    .on(`click`, function () {
                        const params = {
                            type: $('[name=type]:checked').val(),
                            mode: mode
                        };

                        $.post(Routing.generate("settings_edit_status_translations_api", true), params)
                            .then(response => {
                                $modalEditTranslations.find(`.modal-body`).html(response.html);
                                $modalEditTranslations.modal('show');
                            });
                    });
            }
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $addRow.removeClass('d-none');
            $filtersContainer.removeClass('d-none');
            if ($canTranslate) { $translateLabels.addClass('d-none'); }
            $canTranslate = true;
            $pageBody.find('.wii-title').remove();
        },
        columns: getStatusesColumn(mode),
        form: getFormColumn(mode, statusStateOptions, categoryType),
    });

    let submitEditTranslations = $("#submitEditTranslations");
    let urlEditTranslations = Routing.generate('settings_edit_status_translations', true)
    InitModal($modalEditTranslations, submitEditTranslations, urlEditTranslations, {
        success: () => {
            table.toggleEdit(STATE_VIEWING, true);
        }
    });

    $addRow.on(`click`, function() {
        const url = Routing.generate(`settings_edit_status_translations_api`);
        table.setURL(url);
    });

    $addRow
        .off('click')
        .on(`click`, function() {
            table.addRow(true);
        });

    $addButton
        .on('click', function() {
            $canTranslate = false;
        });

    $container.on('change', '[name=state]', function () {
        onStatusStateChange($(this));
    });

    return table;
}

function getStatusesColumn(mode) {
    const singleRequester = [MODE_DISPATCH, MODE_HANDLING, MODE_PURCHASE_REQUEST, MODE_ARRIVAL_DISPUTE].includes(mode) ? ['', ''] : ['x', 's'];
    const singleBuyer = [MODE_PURCHASE_REQUEST].includes(mode) ? [`à`, `l'acheteur`] : [`aux`, `acheteurs`];

    return [
        {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
        {data: `label`, title: `Libellé`, required: true},
        {data: `state`, title: `État`, required: true},
        {data: `type`, title: `Type`, required: true, modes: [MODE_ARRIVAL, MODE_DISPATCH, MODE_HANDLING]},
        {data: `comment`, title: `Commentaire litige`, modes: [MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE]},
        {
            data: `defaultStatut`,
            title: `<div>Statut<br/>par défaut</div>`,
            modes: [MODE_ARRIVAL, MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE, MODE_HANDLING, MODE_PURCHASE_REQUEST]},
        {
            data: `sendMailBuyers`,
            title: `<div class='small-column'>Envoi d'emails ${singleBuyer[0]} ${singleBuyer[1]}</div>`,
            modes: [MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE, MODE_PURCHASE_REQUEST]
        },
        {
            data: `sendMailRequesters`,
            title: `<div class='small-column'>Envoi d'emails au${singleRequester[0]} demandeur${singleRequester[1]}</div>`,
            modes: [MODE_ARRIVAL_DISPUTE, MODE_RECEPTION_DISPUTE, MODE_HANDLING, MODE_PURCHASE_REQUEST, MODE_DISPATCH]
        },
        {
            data: `sendMailDest`,
            title: `<div class='small-column'>Envoi d'emails aux destinataires</div>`,
            modes: [MODE_HANDLING, MODE_DISPATCH]
        },
        {
            data: `automaticReceptionCreation`,
            title: `<div class='small-column' style="max-width: 160px !important;">Création automatique d'une réception</div>`,
            modes: [MODE_PURCHASE_REQUEST]
        },
        {
            data: `needsMobileSync`,
            title: `<div class='small-column'>Synchronisation nomade</div>`,
            modes: [MODE_HANDLING, MODE_DISPATCH]
        },
        {
            data: `commentNeeded`,
            title: `<div class='small-column'>Commentaire obligatoire sur nomade</div>`,
            modes: [MODE_HANDLING]
        },
        {data: `order`, class: `maxw-70px`, title: `Ordre`, required: true},
    ].filter(({modes}) => !modes || modes.indexOf(mode) > -1);
}

function getFormColumn(mode, statusStateOptions, categoryType){
    return {
        actions: `
            <button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>
            <input type='hidden' name='mode' class='data' value='${mode}'/>
        `,
        label: `<input type='text' name='label' class='form-control data needed' data-global-error="Libellé"/>`,
        state: `<select name='state' class='data form-control needed select-size'>${statusStateOptions}</select>`,
        type: categoryType
            ? `
                <select name='type'
                        style="min-width: 150px"
                        class='form-control data'
                        required
                        data-s2='types' data-parent="body"
                        data-no-search
                        data-min-length="0"
                        data-include-params-parent='tr'
                        data-include-params='input[name=categoryType]'>
                </select>
                <input type='hidden' name='categoryType' value='${categoryType}'/>
            `
            : null,
        comment: `<input type='text' name='comment' class='form-control data'/>`,
        defaultStatut: `<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data'/></div>`,
        sendMailBuyers: `<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data'/></div>`,
        sendMailRequesters: `<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data'/></div>`,
        needsMobileSync: `<div class='checkbox-container'><input type='checkbox' name='needsMobileSync' class='form-control data'/></div>`,
        commentNeeded: `<div class='checkbox-container'><input type='checkbox' name='commentNeeded' class='form-control data'/></div>`,
        sendMailDest: `<div class='checkbox-container d-none'><input type='checkbox' name='sendMailDest' class='form-control data'/></div>`,
        automaticReceptionCreation: `<div class='checkbox-container'><input type='checkbox' name='automaticReceptionCreation' class='form-control data'/></div>`,
        order: `<input type='number' name='order' min='1' class='form-control data needed px-2 text-center' data-global-error="Ordre" data-no-arrow/>`,
    };
}

function initializeStatusesByTypes($container, canEdit, mode) {
    const categoryType = $container.find('[name=category-type]').val();
    const $typeFilters = $container.find('[name=type]');
    const $addButton = $container.find(`.add-row-button`);
    const $filtersContainer = $container.find('.filters-container');
    const $pageBody = $container.find('.page-body');

    const table = initializeStatuses($container, canEdit, mode, categoryType);

    $typeFilters
        .off('change')
        .on('change', function() {
            const $type = $(this);
            const type = $type.val();
            const url = Routing.generate(`settings_statuses_api`, {mode, type})
            table.setURL(url);
        });

    $addButton
        .on('click', function() {
            $filtersContainer.addClass('d-none');
            $pageBody.prepend('<div class="header wii-title">Ajouter des statuts</div>');
        });
}

function onStatusStateChange($select) {
    const $form = $select.closest('tr');
    const $needMobileSync = $form.find('[name=needsMobileSync]');
    const $automaticReceptionCreation = $form.find('[name=automaticReceptionCreation]');
    const disabledNeedMobileSync = $select
        .find(`option[value=${$select.val()}]`)
        .data('need-mobile-sync-disabled');
    const disabledAutomaticReceptionCreation = $select
        .find(`option[value=${$select.val()}]`)
        .data('automatic-reception-creation-disabled');

    $needMobileSync.prop('disabled', Boolean(disabledNeedMobileSync));
    if (disabledNeedMobileSync) {
        $needMobileSync.prop('checked', false);
    }

    $automaticReceptionCreation.toggleClass(`d-none`, Boolean(disabledAutomaticReceptionCreation));
}
