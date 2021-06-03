$(function () {
    initPageDataTable();
});

function initPageDataTable() {
    let pathSensorWrapper = Routing.generate('sensor_wrapper_api', true);
    let sensorWrapperTableConfig = {
        processing: true,
        serverSide: true,
        order: [['lastLift', 'desc']],
        ajax: {
            "url": pathSensorWrapper,
            "type": "POST"
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {data: 'actions', name: 'Actions', title: '', className: 'noVis', orderable: false},
            {data: 'type', name: 'type', title: 'Type'},
            {data: 'profile', name: 'profile', title: 'Profil'},
            {data: 'name', name: 'name', title: 'Nom'},
            {data: 'code', name: 'code', title: 'Code'},
            {data: 'lastLift', name: 'lastLift', title: 'Dernière remontée'},
            {data: 'batteryLevel', name: 'batteryLevel', title: 'Niveau de batterie'},
            {data: 'manager', name: 'manager', title: 'Gestionnaire'},
        ]
    };
    return initDataTable('tableSensorWrapper', sensorWrapperTableConfig);
}

function sensorWrapperProvision() {

}

function associatedMessages($button) {
    const id = $button.data('id');
    window.location.href = Routing.generate('sensor_message_index', {id: id}, true);
}

function associatedElements($button) {
    const id = $button.data('id');
    window.location.href = Routing.generate('sensors_pairing_index', {id: id}, true);
}

function deleteSensorWrapper($button) {

}
