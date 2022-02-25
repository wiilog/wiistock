import EditableDatatable, {MODE_CLICK_EDIT, MODE_NO_EDIT, SAVE_MANUALLY} from "../../editatable";

const $managementButtons = $(`.save-settings, .discard-settings`);

export function initializeArrivalDisputeStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, 'arrival-dispute');
}

export function initializeReceptionDisputeStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, 'reception-dispute');
}

export function initializePurchaseRequestStatuses($container, canEdit) {
    initializeStatuses($container, canEdit, 'purchase-request');
}

function initializeStatuses($container, canEdit, mode) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);
    const $statutStateOptions = JSON.parse($(`#statut_state_options`).val());
    const tableSelector = `#${mode}-statuses-table`;
    const route = Routing.generate(`settings_statuses_api`, {mode})

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
        },
        onEditStop: () => {
            $managementButtons.addClass('d-none');
            $tableHeader.removeClass('d-none');
        },
        columns: getStatusesColumn(mode),
        form: getFormColumn(mode, $statutStateOptions),
    });

    $addButton.on(`click`, function() {
        table.addRow(true);
    });
}

function getStatusesColumn(mode){
    const column = [
        {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
        {data: `label`, title: `Libelle`, required: true},
        {data: `state`, title: `Etat`, required: true},
    ];

    if(mode !== 'purchase-request'){
        column.push({data: `comment`, title: `Commentaire litige`});
    }

    column.push([
        {data: `defaultStatut`, title: `Statut par défaut`},
        {data: `sendMailBuyers`, title: `<div class='small-column'>Envoi de mails aux acheteurs</div>`},
        {data: `sendMailRequesters`, title: `<div class='small-column'>Envoi de mails aux demandeurs</div>`},
        {data: `sendMailDest`, title: `<div class='small-column'>Envoi de mails aux destinataires</div>`},
        {data: `order`, title: `Ordre`, required: true},
    ]);

    return column;
}

function getFormColumn(mode, $statutStateOptions){
    var form = {
        actions: `
                <button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>
                <input type='hidden' name='mode' class='data' value='${mode}'/>
            `,
        label: `<input type='text' name='label' class='form-control data needed' data-global-error="Libellé"/>`,
        state: `<select name='state' class='data form-control needed select-size'>${$statutStateOptions}</select>`,};
    if(mode !== 'purchase-request'){
        form.comment = `<input type='text' name='comment' class='form-control data'/>`;
    }
    form.defaultStatut =`<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data'/></div>`;
    form.sendMailBuyers = `<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data'/></div>`;
    form.sendMailRequesters = `<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data'/></div>`;
    form.sendMailDest = `<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data'/></div>`;
    form.order = `<input type='number' name='order' min='1' class='form-control data needed'  data-global-error="Ordre"/>`;
    return form;
}
