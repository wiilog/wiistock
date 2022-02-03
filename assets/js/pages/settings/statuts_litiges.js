import EditableDatatable, {MODE_DOUBLE_CLICK, MODE_NO_EDIT, SAVE_MANUALLY} from "../../editatable";

const $managementButtons = $(`.save-settings, .discard-settings`);

export function initializeStatutsLitigeArrivages($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);
    const $statutStateOptions = JSON.parse($(`#statut_state_options`).val());

    const table = EditableDatatable.create(`#table-statuts-litige-arrivage`, {
        route: Routing.generate(`settings_statuts_litiges_arrivages_api`, true),
        deleteRoute: `settings_statuts_litiges_arrivages_delete`,
        mode: canEdit ? MODE_DOUBLE_CLICK : MODE_NO_EDIT,
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
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libelle`},
            {data: `state`, title: `Etat`},
            {data: `comment`, title: `Commentaire litige`},
            {data: `defaultStatut`, title: `Statut par défaut`},
            {data: `sendMailBuyers`, title: `<div class='small-column'>Envoi de mails aux acheteurs</div>`},
            {data: `sendMailRequesters`, title: `<div class='small-column'>Envoi de mails aux demandeurs</div>`},
            {data: `sendMailDest`, title: `<div class='small-column'>Envoi de mails aux destinataires</div>`},
            {data: `order`, title: `Ordre`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed'  data-global-error="Libellé"/>`,
            state: `<select name='state' class='data form-control needed select-size'>`+$statutStateOptions+`</select>`,
            comment: `<input type='text' name='comment' class='form-control data needed'  data-global-error="Commentaire"/>`,
            defaultStatut: `<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data'/></div>`,
            sendMailBuyers: `<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data'/></div>`,
            sendMailRequesters: `<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data'/></div>`,
            sendMailDest: `<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data'/></div>`,
            order: `<input type='number' name='order' min='1' class='form-control data needed'  data-global-error="Ordre"/>`,
        },
    });

    $addButton.on(`click`, function() {
        table.addRow();
    });
}

export function initializeStatutsLitigeReceptions($container, canEdit) {
    const $addButton = $container.find(`.add-row-button`);
    const $tableHeader = $(`.wii-page-card-header`);
    const $statutStateOptions = JSON.parse($(`#statut_state_options`).val());

    const table = EditableDatatable.create(`#table-statuts-litige-reception`, {
        route: Routing.generate(`settings_statuts_litiges_receptions_api`, true),
        deleteRoute: `settings_statuts_litiges_receptions_delete`,
        mode: canEdit ? MODE_DOUBLE_CLICK : MODE_NO_EDIT,
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
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `label`, title: `Libelle`},
            {data: `state`, title: `Etat`},
            {data: `comment`, title: `Commentaire litige`},
            {data: `defaultStatut`, title: `Statut par défaut`},
            {data: `sendMailBuyers`, title: `<div class='small-column'>Envoi de mails aux acheteurs</div>`},
            {data: `sendMailRequesters`, title: `<div class='small-column'>Envoi de mails aux demandeurs</div>`},
            {data: `sendMailDest`, title: `<div class='small-column'>Envoi de mails aux destinataires</div>`},
            {data: `order`, title: `Ordre`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            label: `<input type='text' name='label' class='form-control data needed'  data-global-error="Libellé"/>`,
            state: `<select name='state' class='data form-control needed select-size'>`+$statutStateOptions+`</select>`,
            comment: `<input type='text' name='comment' class='form-control data needed'  data-global-error="Commentaire"/>`,
            defaultStatut: `<div class='checkbox-container'><input type='checkbox' name='defaultStatut' class='form-control data'/></div>`,
            sendMailBuyers: `<div class='checkbox-container'><input type='checkbox' name='sendMailBuyers' class='form-control data'/></div>`,
            sendMailRequesters: `<div class='checkbox-container'><input type='checkbox' name='sendMailRequesters' class='form-control data'/></div>`,
            sendMailDest: `<div class='checkbox-container'><input type='checkbox' name='sendMailDest' class='form-control data'/></div>`,
            order: `<input type='number' name='order' min='1' class='form-control data needed'  data-global-error="Ordre"/>`,
        },
    });

    $addButton.on(`click`, function() {
        table.addRow();
    });
}
