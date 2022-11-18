import EditableDatatable, {MODE_CLICK_EDIT_AND_ADD, MODE_NO_EDIT, SAVE_MANUALLY} from "../../editatable";

const $managementButtons = $(`.save-settings, .discard-settings`);

export function initializeTransportRound($container, canEdit) {
    const table = EditableDatatable.create(`#table-starting-hours`, {
        route: Routing.generate('settings_starting_hours_api', true),
        mode: canEdit ? MODE_CLICK_EDIT_AND_ADD : MODE_NO_EDIT,
        save: SAVE_MANUALLY,
        needsPagingHide: true,
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: 'id', name: 'id', title: '', className: 'noVis hideOrder d-none', orderable: false},
            {data: `hour`, title: `Heure<br><div class='wii-small-text'>Horaire sous la forme HH:MM</div>`},
            {data: `deliverers`, title: `Livreur(s)`},
        ],
        form: {
            actions: `<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>`,
            id: ``,
            hour: `<input name='hour' class='form-control data' required data-global-error='Heure'/>`,
            deliverers: `
                <select name='deliverers'
                        required
                        data-s2="user"
                        data-parent="body"
                        class='form-control data'
                        data-global-error='Livreur(s)'
                        data-other-params-deliverer-only="1"
                        multiple='multiple'/></select>`,
        },
    });

    $('select[name="TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES"]').on('change', function() {
        const $target = $('select[name="TRANSPORT_ROUND_COLLECT_WORKFLOW_ENDING_MOTIVE"]');
        const targetValues = $target.val();
        const possibleValues = $(this).val();

        $target.empty();
        possibleValues.forEach((value) => {
            let newOption = new Option(value, value, false, targetValues.includes(value));
            $target.append(newOption).trigger('change');
        })
    });
}
