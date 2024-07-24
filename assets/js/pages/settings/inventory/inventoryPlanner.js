import AJAX, {DELETE, GET, POST} from "@app/ajax";
import Form from "@app/form";
import Routing from '@app/fos-routing';
import {initFormAddInventoryLocations} from "@app/pages/inventory-mission/form-add-inventory-locations";
import {toggleFrequencyInput} from '@app/pages/settings/utils';
import {initDataTable} from "@app/datatable";

let tableInventoryPanning;

global.editMissionPlan = editMissionPlan;
global.deleteInventoryMission = deleteInventoryMission;
global.cancelInventoryMission = cancelInventoryMission;

export function initializeInventoryPlanificatorTable($container) {
    const $missionPlansTable = $container.find('table#missionPlansTable');
    const tableInventoryPannerConfig = {
        ajax: {
            "url": Routing.generate('settings_mission_plans_api', true),
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
            {data: `requester`, title: `Demandeur`},
            {data: `lastExecution`, title: `Dernière exécution`},
            {data: `active`, title: `Actif`},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    tableInventoryPanning = initDataTable($missionPlansTable, tableInventoryPannerConfig);

    const $modalFormInventoryPlanner = $('#modalFormInventoryPlanner');
    Form
        .create($modalFormInventoryPlanner)
        .addProcessor((data, errors, $form) => {
            const $addInventoryLocationsModule = $form.find('.add-inventory-location-container');
            const $locationTable = $addInventoryLocationsModule.find('table');
            const locations = $locationTable.DataTable().column(0).data().toArray();
            const $missionType = $modalFormInventoryPlanner.find('[name=missionType]:checked').val() || $modalFormInventoryPlanner.find('[name=missionType]').val();

            if($missionType === 'location'){
                if (locations.length === 0) {
                    errors.push({
                        message: `Vous devez sélectionner au moins un emplacement`,
                    });
                } else {
                    data.append('locations', locations);
                }
            }
        })
        .addProcessor((data, errors, $form) => {
            const $frequency = $form.find('input[type="radio"][name="frequency"]:checked');
            if (!$frequency.val()) {
                errors.push({
                    message: `Veuillez sélectionner une fréquence`,
                });
            }
            data.append('frequency', $frequency.val());
        })
        .onSubmit((data, form) => {
            form.loading(() => {
                return AJAX
                    .route(`POST`, `mission_plans_form`, {})
                    .json(
                        data
                    )
                    .then((response) => {
                        if (response.success) {
                            tableInventoryPanning.ajax.reload();
                            $modalFormInventoryPlanner.modal('hide');
                        }
                    });
            });
        });
}

function editMissionPlan($button) {
    const $modalEditInventoryPanner = $('#modalFormInventoryPlanner');

    const missionPlan = $button.data('id');
    const $wrapperLoader = missionPlan ? $button.closest('#missionPlansTable_wrapper') : $button;

    const params = missionPlan ? {missionPlan} : {};

    wrapLoadingOnActionButton($wrapperLoader, () => (
        AJAX
            .route(GET, 'mission_plans_form_template', params)
            .json()
            .then(({html}) => {
                $modalEditInventoryPanner.find('.modal-body').html(html);
                initModalContent($modalEditInventoryPanner);
                $modalEditInventoryPanner.modal('show');
            })
    ));
}

function initModalContent($modal) {

    const $checkedFrequency = $modal.find('[name=frequency]:checked');
    if ($checkedFrequency.exists()) {
        toggleFrequencyInput($checkedFrequency);
    }

    const $locationTypeForm = $modal.find('.location-type-form');
    const $articleTypeForm = $modal.find('.article-type-form');

    initFormAddInventoryLocations($locationTypeForm.find('.add-inventory-location-container'));

    const $missionType = $modal.find('[name=missionType]');
    $missionType.on('change', function (input) {
        const $input = $(input.target);
        if ($input.val() === 'location') {
            $locationTypeForm.removeClass('d-none');
            $articleTypeForm.addClass('d-none');
        } else if ($input.val() === 'article') {
            $articleTypeForm.removeClass('d-none');
            $locationTypeForm.addClass('d-none');
        }
    });
}

function cancelInventoryMission($button) {
    const mission = $button.data('id');
    Modal.confirm({
        title: 'Annuler la planification',
        message: "Confirmez-vous l'annulation cette planification de missions d'inventaire ?",
        table: tableInventoryPanning,
        ajax: {
            method: DELETE,
            route: 'mission_plans_cancel',
            params: { mission },
        },
        validateButton: {
            color: 'danger',
        },
    });
}

function deleteInventoryMission($button) {
    const mission = $button.data('id');
    Modal.confirm({
        title: 'Supprimer la planification',
        message: "Confirmez-vous la suppression cette planification de missions d'inventaire ?",
        table: tableInventoryPanning,
        ajax: {
            method: DELETE,
            route: 'mission_plans_delete',
            params: { mission },
        },
        validateButton: {
            color: 'danger',
        },
    });
}
