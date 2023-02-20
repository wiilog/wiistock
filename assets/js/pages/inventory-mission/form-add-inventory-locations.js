import AJAX, {POST} from "@app/ajax";

global.initFormAddInventoryLocations = initFormAddInventoryLocations;
global.addRowInventoryLocations = addRowInventoryLocations;

export function initFormAddInventoryLocations($container){
    const $tableLocations = $container.find('.tableLocationsInventoryMission');

    initDataTable($tableLocations, {
        lengthMenu: [10, 25, 50],
        columns: [
            {data: 'id', name: 'id', title: 'id', visible: false },
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

    $container.find('.add-button').on('click', function(){
        wrapLoadingOnActionButton($(this), () => {
            let ids = [];
            $(this).closest('div').find('select').find('option:selected').each(function() {
                ids.push($(this).val());
                $(this).parent().empty();
            });
            return AJAX
                .route(POST, 'add_locations_or_zones_to_mission_datatable', {
                    buttonType: $(this).data('type'),
                    dataIdsToDisplay: ids,
                })
                .json()
                .then((response) => {
                    if(response.success){

                        addRowInventoryLocations($tableLocations, response.data)
                    }
                });
        })
    });
}

export function addRowInventoryLocations($table, dataToDisplay){
    const tableLocationsDatatable = $table.DataTable();
    const tableLocationsData = tableLocationsDatatable.column(0).data().toArray();
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
}
