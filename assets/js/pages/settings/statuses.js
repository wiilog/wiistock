import EditableDatatable, {MODE_CLICK_EDIT, MODE_NO_EDIT, SAVE_MANUALLY} from "../../editatable";

const MODE_ARRIVAL_DISPUTE = 'arrival-dispute';
const MODE_RECEPTION_DISPUTE = 'reception-dispute';
const MODE_PURCHASE_REQUEST = 'purchase-request';
const MODE_ARRIVAL = 'arrival';
const MODE_DISPATCH = 'dispatch';
const MODE_HANDLING = 'handling';

const $managementButtons = $(`.save-settings, .discard-settings`);

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
    const $filtersContainer = $container.find('.filters-container');
    const $pageBody = $container.find('.page-body');
    const $tableHeader = $(`.wii-page-card-header`);
    const $statusStateOptions = $container.find('[name=status-state-options]');
    const statusStateOptions = JSON.parse($statusStateOptions.val());
    const tableSelector = `#${mode}-statuses-table`;
    const route = Routing.generate(`settings_statuses_api`, {mode});

    const table = EditableDatatable.create(tableSelector, {
        route,
        deleteRoute: `settings_delete_status`,
        mode: canEdit ? MODE_CLICK_EDIT : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        search: false,
        paginate: false,
        scrollY: false,
        scrollX: false,
        onInit: () => {
            $addButton.removeClass(`d-none`);
        },
        onEditStart: () => {
            $managementButtons.removeClass('d-none');
            $tableHeader.addClass('d-none');
            $filtersContainer.addClass('d-none');
            $pageBody.prepend('<div class="header wii-title">Ajouter des statuts</div>');
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $tableHeader.removeClass('d-none');
            $filtersContainer.removeClass('d-none');
            $pageBody.find('.wii-title').remove();
        },
        columns: getStatusesColumn(mode),
        form: getFormColumn(mode, statusStateOptions, categoryType),
    });

    $addButton.on(`click`, function() {
        table.addRow(true);
    });

    $container.on('change', '[name=state]', function () {
        onStatusStateChange($(this));
    });

    return table;
}

function getStatusesColumn(mode) {
    const singleRequester = [MODE_DISPATCH].includes(mode) ? ['', ''] : ['x', 's'];
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
            title: `<div class='small-column'>Envoi d'emails aux acheteurs</div>`,
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
            modes: [MODE_ARRIVAL_DISPUTE, MODE_HANDLING, MODE_DISPATCH]
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
        {data: `order`, title: `Ordre`, required: true},
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
                        data-s2='types'
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
        sendMailDest: `<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data'/></div>`,
        automaticReceptionCreation: `<div class='checkbox-container'><input type='checkbox' name='automaticReceptionCreation' class='form-control data'/></div>`,
        order: `<input type='number' name='order' min='1' class='form-control data needed px-2 text-center' data-global-error="Ordre" data-no-arrow/>`,
    };
}

function initializeStatusesByTypes($container, canEdit, mode) {
    const categoryType = $container.find('[name=category-type]').val();
    const table = initializeStatuses($container, canEdit, mode, categoryType);
    const $typeFilters = $container.find('[name=type]');
    const $addButton = $container.find('.add-row-button');

    $typeFilters
        .off('change')
        .on('change', function() {
            const $type = $(this);
            const type = $type.val();
            const url = Routing.generate(`settings_statuses_api`, {mode, type})
            table.setURL(url);
        });

    $addButton
        .off('click')
        .on('click', function() {
            table.addRow(true);
        });
}

function onStatusStateChange($select) {
    const $form = $select.closest('tr');
    const $needMobileSync = $form.find('[name=needsMobileSync]');
    const needsToDisabled = $select
        .find(`option[value=${$select.val()}]`)
        .data('need-mobile-sync-disabled');

    $needMobileSync.prop('disabled', Boolean(needsToDisabled));
    if (needsToDisabled) {
        $needMobileSync.prop('checked', false);
    }
}
