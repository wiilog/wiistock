$(function () {
    const sensorWrapperTable = initPageDataTable();

    initPageModals([sensorWrapperTable]);
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

function associatedMessages($button) {

}

function associatedObjects($button) {

}

function deleteSensorWrapper($button) {
    const wrapperId = $button.data('id');
    const $deleteModal = $('#modalDeleteSensorWrapper');

    $deleteModal
        .find('[name="id"]')
        .val(wrapperId);

    $deleteModal.modal('show');
}

function initPageModals(tables) {
    const $modal = $('#modalNewSensorWrapper');

    Select2Old.init(
        $modal.find('[name="sensor"]'),
        '',
        1,
        {
            route: 'get_sensor_data_by_code',
            param: { onlyAvailable: 1 }
        });
    Select2Old.user($modal.find('[name="manager"]'));

    $modal.on('show.bs.modal', function () {
        const $sensor = $modal.find('[name="sensor"]');
        $sensor.val(null).trigger('change');
        clearModal($modal);
        onSensorCodeChange($sensor);
    });

    InitModal($modal, $modal.find('.submit-button'), Routing.generate('sensor_wrapper_new'), {tables});


    let $modalEditSensorWrapper = $("#modalEditSensorWrapper");
    let urlEditSensorWrapper = Routing.generate('sensor_wrapper_edit', true);
    InitModal($modalEditSensorWrapper, $modalEditSensorWrapper.find('.submit-button'), urlEditSensorWrapper, {tables});

    let $modalDeleteSensorWrapper = $("#modalDeleteSensorWrapper");
    let urlDeleteSensorWrapper = Routing.generate('sensor_wrapper_delete', true);
    InitModal($modalDeleteSensorWrapper, $modalDeleteSensorWrapper.find('.submit-button'), urlDeleteSensorWrapper, {tables});
}

function onSensorCodeChange($sensor) {
    const $modal = $sensor.closest('.modal');
    const $sensorRequiredDiv = $modal.find('.sensor-required');
    const $sensorData = $modal.find('.sensor-data');

    const [sensor] = $sensor.select2('data') || [];

    if (sensor) {
        const {type, profile, frequency} = sensor;
        $sensorData.find('.sensor-data-type').html(type);
        $sensorData.find('.sensor-data-profile').html(profile);
        $sensorData.find('.sensor-data-frequency').html(frequency);

        $sensorRequiredDiv.removeClass('d-none');
    }
    else {
        $sensorRequiredDiv.addClass('d-none');
        $sensorData.find('.sensor-data-value').html('');
    }
}
