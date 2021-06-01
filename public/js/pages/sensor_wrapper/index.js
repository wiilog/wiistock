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
            {"data": 'actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'type', 'name': 'type', 'title': 'Type'},
            {"data": 'profile', 'name': 'profile', 'title': 'Profil'},
            {"data": 'name', 'name': 'name', 'title': 'Nom'},
            {"data": 'code', 'name': 'code', 'title': 'Code'},
            {"data": 'lastLift', 'name': 'lastLift', 'title': 'Dernière remontée'},
            {"data": 'batteryLevel', 'name': 'batteryLevel', 'title': 'Niveau de batterie'},
            {"data": 'manager', 'name': 'manager', 'title': 'Gestionnaire'},
        ]
    };
    return initDataTable('tableSensorWrapper', sensorWrapperTableConfig);
}

function sensorWrapperProvision() {

}

function associatedMessages($button) {

}

function associatedObjects($button) {

}

function deleteSensorWrapper($button) {

}
