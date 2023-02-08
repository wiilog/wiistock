import AJAX, {GET} from "@app/ajax";
import Form from "@app/form";
import modal from "bootstrap/js/src/modal";

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
}

function initModalContent($modal) {
    const locationTypeForm = $modal.find('#locationTypeForm');
    const articleTypeForm = $modal.find('#articleTypeForm');

    const tableLocations = $modal.find('#tableLocations');

    initModalTableLocations($modal);
    initAddLocationAndZoneForm(tableLocations, $modal);


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

function initAddLocationAndZoneForm(tableLocations, $modal) {
    let locationTypeForm = $('#locationTypeForm');

    Form.create($modal)
        .onSubmit((data, form) => {
            if ($modal.find('input[type="radio"][name="missionType"]:checked').val() === 'location') {
                data.append('locations', JSON.stringify(tableLocations.DataTable().column(0).data().toArray()));
            }
            wrapLoadingOnActionButton(locationTypeForm.find('button[type=submit]'), () => {
                return AJAX
                    .route(`POST`, `post_mission_rules_form`, {})
                    .json(
                        data
                    )
                    .then((response) => {
                        if (response.success) {
                            tableInventoryPanning.ajax.reload();
                            $modal.modal('hide');
                        }
                    });
            });
        });

    locationTypeForm.find('.add-button').on('click', function () {
        wrapLoadingOnActionButton($(this), () => {
                const buttonType = $(this).data('type');
                let ids = [];
                $(this).closest('.row').find('select').find('option:selected').each(function () {
                    ids.push($(this).val());
                    $(this).parent().empty();
                });
                return AJAX.route('POST', 'add_locations_or_zones_to_mission_datatable', {
                    buttonType,
                    dataIdsToDisplay: ids,
                })
                    .json()
                    .then((response) => {
                        if (response.success) {
                            initModalTableLocations($modal, response.data);
                        }
                    });
            }
        )
    });
}


function initModalTableLocations($modal, dataToDisplay = null) {
    const tableLocations = $modal.find('#tableLocations');

    if (dataToDisplay) {
        const tableLocationsDatatable = tableLocations.DataTable();
        const tableLocationsData = tableLocationsDatatable.column(1).data().toArray();
        for (const lineToAdd of dataToDisplay) {
            if (Array.isArray(lineToAdd)) {
                for (const line of lineToAdd) {
                    if (!tableLocationsData.includes(line.location)) {
                        tableLocationsDatatable.row.add(line).draw(false);
                    }
                }
            } else {
                if (!tableLocationsData.includes(lineToAdd.location)) {
                    tableLocationsDatatable.row.add(lineToAdd).draw(false);
                }
            }
        }
    } else {
        initDataTable('tableLocations', {
            lengthMenu: [10, 25, 50],
            columns: [
                {data: 'id', name: 'id', title: 'id', visible: false},
                {data: 'zone', name: 'zone', title: 'Zone'},
                {data: 'location', name: 'location', title: 'Emplacement'},
            ],
            order: [
                ['location', 'asc'],
            ],
            domConfig: {
                removeInfo: true
            },
            paging: true,
            searching: false,
        });
    }
}

function editMissionRule($button) {
    const $modalEditInventoryPanner = $('#modalFormInventoryPlanner');

    const $wrapperLoader = $button.data('id') ? $button.closest('#missionRulesTable_wrapper') : $button;
    wrapLoadingOnActionButton($wrapperLoader, () => (
        AJAX
            .route('GET', 'get_mission_rules_form', {
                missionRuleId: $button.data('id'),
            })
            .json()
            .then(({html}) => {
                $modalEditInventoryPanner.find('.modal-body').html(html);
                initModalContent($modalEditInventoryPanner);
                $modalEditInventoryPanner.modal('show');
            })
    ));
}
