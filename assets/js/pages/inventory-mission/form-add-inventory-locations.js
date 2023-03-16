import AJAX, {POST} from "@app/ajax";

global.initFormAddInventoryLocations = initFormAddInventoryLocations;

export function initFormAddInventoryLocations($container) {
    const $tableLocations = $container.find('table');

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

    $container
        .find('.add-button')
        .off('click.add-inventory-location')
        .on('click.add-inventory-location', function(){
            const $button = $(this);
            wrapLoadingOnActionButton($button, () => {
                const $select = $button.closest('div').find('select');
                const $selectedOptions = $select.find('option:selected');
                const ids = $selectedOptions
                    .map((_, option) => $(option).val())
                    .toArray();
                return AJAX
                    .route(POST, 'add_locations_or_zones_to_mission_datatable', {
                        buttonType: $(this).data('type'),
                        dataIdsToDisplay: ids,
                    })
                    .json()
                    .then((response) => {
                        if(response.success){
                            $select.find('option').remove();
                            $select.val(null).trigger('change');
                            addRowInventoryLocations($tableLocations, response.data)
                        }
                    });
            })
        });
}

function addRowInventoryLocations($table, dataToDisplay){
    const tableLocationsDatatable = $table.DataTable();
    const tableLocationsData = tableLocationsDatatable.column(0).data().toArray();

    for (const lineToAdd of dataToDisplay){
        if(Array.isArray(lineToAdd)){
            for (const line of lineToAdd){
                if(!tableLocationsData.includes(line.id)){
                    tableLocationsDatatable.row.add(line).draw(false);
                }
            }
        } else {
            if(!tableLocationsData.includes(lineToAdd.id)){
                tableLocationsDatatable.row.add(lineToAdd).draw(false);
            }
        }
    }
}
