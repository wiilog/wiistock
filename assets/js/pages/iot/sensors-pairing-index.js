$(function () {
    initPageDataTable();
});

function initPageDataTable() {
    let pathPairing = Routing.generate('sensors_pairing_api', {sensor: id}, true);
    let pairingTableConfig = {
        processing: true,
        serverSide: true,
        order: [['end', 'desc']],
        ajax: {
            "url": pathPairing,
            "type": "POST"
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        columns: [
            {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
            {data: 'element', name: 'element', title: 'Elément'},
            {data: 'start', name: 'start', title: 'Date d\'association'},
            {data: 'end', name: 'end', title: 'Date de fin d\'association'},
        ]
    };
    return initDataTable('tablePairing', pairingTableConfig);
}
