import AJAX, {GET} from "@app/ajax";
import Form from "@app/form";
import {initFormAddInventoryLocations} from "@app/pages/inventory-mission/form-add-inventory-locations";

let tableInventoryPanning;

global.editMissionRule = editMissionRule;

export function initializeInventoryPlanificatorTable($container) {
    const tableInventoryPannerConfig = {
        ajax: {
            "url": Routing.generate('settings_mission_rules_api', true),
            "type": GET
        },
        order: [[1, "asc"]],
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `missionType`, title: `Type de mission`},
            {data: `label`, title: `Libellé`, required: true},
            {data: `periodicity`, title: `Périodicité`},
            {data: `categories`, title: `Catégorie(s)`},
            {data: `duration`, title: `Durée`},
            {data: `creator`, title: `Créateur`},
            {data: `lastExecution`, title: `Dernière exécution`},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    tableInventoryPanning = initDataTable(`missionRulesTable`, tableInventoryPannerConfig);

    const $modalEditInventoryPanner = $('#modalFormInventoryPlanner');
    Form
        .create($modalEditInventoryPanner)
        .onSubmit((data, form) => {
            if (data.get('missionType') === 'location') {
                const locations = $modalEditInventoryPanner.find('.tableLocationsInventoryMission').DataTable().column(0).data().toArray();
                if (locations.length === 0) {
                    Flash.add('danger', 'Vous devez sélectionner au moins un emplacement');
                    return ;
                } else {
                    data.append('locations', JSON.stringify(locations));
                }
            }
            data.append('frequency', $modalEditInventoryPanner.find('input[type="radio"][name="frequency"]:checked').val());
            form.loading(() => {
                return AJAX
                    .route(`POST`, `mission_rules_form`, {})
                    .json(
                        data
                    )
                    .then((response) => {
                        if (response.success) {
                            tableInventoryPanning.ajax.reload();
                            $modalEditInventoryPanner.modal('hide');
                        }
                        else {
                            Flash.add('danger', response.message);
                        }
                    });
            });
        });
}

function editMissionRule($button) {
    const $modalEditInventoryPanner = $('#modalFormInventoryPlanner');

    const missionRule = $button.data('id');
    const $wrapperLoader = missionRule ? $button.closest('#missionRulesTable_wrapper') : $button;

    const params = missionRule ? {missionRule} : {};

    wrapLoadingOnActionButton($wrapperLoader, () => (
        AJAX
            .route(GET, 'mission_rules_form_template', params)
            .json()
            .then(({html}) => {
                $modalEditInventoryPanner.find('.modal-body').html(html);
                initModalContent($modalEditInventoryPanner);
                $modalEditInventoryPanner.modal('show');
            })
    ));
}

function initModalContent($modal) {
    const locationTypeForm = $modal.find('#locationTypeForm');
    const articleTypeForm = $modal.find('#articleTypeForm');

    initFormAddInventoryLocations($modal, null);

    $('input[type=radio][name=missionType]').on('change', function (input) {
        const $input = $(input.target);
        if ($input.val() === 'location') {
            locationTypeForm.removeClass('d-none');
            articleTypeForm.addClass('d-none');
        } else if ($input.val() === 'article') {
            articleTypeForm.removeClass('d-none');
            locationTypeForm.addClass('d-none');
        }
    });
}
