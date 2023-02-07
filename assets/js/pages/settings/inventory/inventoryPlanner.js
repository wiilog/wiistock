import AJAX, {GET} from "@app/ajax";
import Form from "@app/form";

let modalNewInventoryPanner = '#modalNewInventoryPlanner';
let tableInventoryPanning;

export function initializeInventoryPlanificatorTable($container) {
    let $modalNewInventoryPanner = $(modalNewInventoryPlanner);

    const tableInventoryPannerConfig = {
        ajax: {
            "url": Routing.generate('settings_mission_rules_api', true),
            "type": GET
        },
        order: [[1, "asc"]],
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis hideOrder', orderable: false},
            {data: `missionType`, title: `Type de mission`, required: true},
            {data: `label`, title: `Libellé`, required: true},
            {data: `periodicity`, title: `Périodicité`, required: true},
            {data: `categories`, title: `Catégorie(s)`, required: true},
            {data: `duration`, title: `Durée`, required: true},
            {data: `creator`, title: `Créateur`, required: true},
            {data: `lastExecution`, title: `Dernière exécution`, required: true},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    tableInventoryPanning = initDataTable(`missionRulesTable`, tableInventoryPannerConfig);

    const formNewInventoryPanner = Form
        .create(modalNewInventoryPanner, {clearOnOpen: true})
        .submitTo(`POST`, `settings_mission_rules_force`, {
            tableInventoryPanning
        });

    $(`#addNewInventoryPlanner`).on(`click`, () => {
        $modalNewInventoryPanner.modal(`show`);
    });

    const locationTypeForm = $('#locationTypeForm');
    const articleTypeForm = $('#articleTypeForm');

    const tableLocations = $('#tableLocations');
    initModalAddTableLocations();
    initAddLocationAndZoneForm(tableLocations);

    $('input[type=radio][name=missionType]').on('change', function (input) {
        const $input = $(input.target);
        if ($input.val() === 'location') {
            locationTypeForm.removeClass('d-none');
            articleTypeForm.addClass('d-none');
        }
        else if($input.val() === 'article') {
            articleTypeForm.removeClass('d-none');
            locationTypeForm.addClass('d-none');
        }
    });
}

function initAddLocationAndZoneForm(tableLocations){
    let locationTypeForm = $('#locationTypeForm');

    Form.create(locationTypeForm)
        .onSubmit(() => {
            wrapLoadingOnActionButton(locationTypeForm.find('button[type=submit]'),() => {
                return AJAX.route(`POST`, `add_locations_or_zones_to_mission`, {
                    mission,
                    locations: tableLocations.DataTable().column(2).data().toArray()
                })
                    .json()
                    .then((response) => {
                        if(response.success){
                            locationTypeForm.modal('hide');
                            tableLocationMission.ajax.reload();
                        }
                    });
            });
        });

    locationTypeForm.find('.add-button').on('click', function(){
        wrapLoadingOnActionButton($(this), () => {
            const buttonType = $(this).data('type');
            let ids = [];
            $(this).closest('.row').find('select').find('option:selected').each(function() {
                ids.push($(this).val());
                $(this).parent().empty();
            });
            return AJAX.route('POST', 'add_locations_or_zones_to_mission_datatable', {
                buttonType,
                dataIdsToDisplay: ids,
            })
                .json()
                .then((response) => {
                    if(response.success){
                        initModalAddTableLocations(response.data);
                    }
                });
            }
        )
    });
}


function initModalAddTableLocations(dataToDisplay = null){
    const tableLocations = $('#tableLocations');

    if(dataToDisplay){
        const tableLocationsDatatable = tableLocations.DataTable();
        const tableLocationsData = tableLocationsDatatable.column(1).data().toArray();
        for (const lineToAdd of dataToDisplay){
            if(Array.isArray(lineToAdd)){
                for (const line of lineToAdd){
                    if(!tableLocationsData.includes(line.location)){
                        tableLocationsDatatable.row.add(line).draw(false);
                    }
                }
            } else {
                if(!tableLocationsData.includes(lineToAdd.location)){
                    tableLocationsDatatable.row.add(lineToAdd).draw(false);
                }
            }
        }
    } else {
        initDataTable('tableLocations', {
            lengthMenu: [10, 25, 50],
            columns: [
                {data: 'zone', name: 'zone', title: 'Zone'},
                {data: 'location', name: 'location', title: 'Emplacement'},
                {data: 'id', name: 'id', title: 'id', visible: false },
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
