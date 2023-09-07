$(function() {
    initSensorTable();
});

function initSensorTable(){
    const tableSensorMessages = {
        processing: true,
        serverSide: true,
        ajax: {
            url: Routing.generate('sensor_messages_api', {sensor: id}, true),
            type: "POST",
        },
        order: [['date', 'desc']],
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {data: 'date', name: 'date', title: 'Date'},
            {data: 'content', name: 'content', title: 'Donnée principale'},
            {data: 'contentType', name: 'contentType', title: 'Type de donnée'},
            {data: 'event', name: 'event', title: 'Type de message'}
        ],
    };

    initDataTable('tableSensorMessage', tableSensorMessages);
}

function openEvolutionModal($modal) {
    clearModal($modal);
    $modal.modal('message');
}

