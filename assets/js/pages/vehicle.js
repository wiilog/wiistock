$(function() {
    const vehicleTable = initVehicleTable();

    const $modalNewVehicle = $(`#modalNewVehicle`);
    const $submitNewVehicle = $modalNewVehicle.find(`button.submit`);
    const urlNewVehicle = Routing.generate(`vehicle_new`, true);
    InitModal($modalNewVehicle, $submitNewVehicle, urlNewVehicle, {tables: [vehicleTable]});

    const $modalEditVehicle = $(`#modalEditVehicle`);
    const $submitEditVehicle = $(`#submitEditVehicle`);
    const urlEditVehicle = Routing.generate(`vehicle_edit`, true);
    InitModal($modalEditVehicle, $submitEditVehicle, urlEditVehicle, {tables: [vehicleTable]});

    const $modalDeleteVehicle = $(`#modalDeleteVehicle`);
    const $submitDeleteVehicle = $(`#submitDeleteVehicle`);
    const urlDeleteVehicle = Routing.generate(`vehicle_delete`, true);
    InitModal($modalDeleteVehicle, $submitDeleteVehicle, urlDeleteVehicle, {tables: [vehicleTable]});
});

function initVehicleTable() {
    return initDataTable(`vehicleTable_id`, {
        processing: true,
        serverSide: true,
        paging: true,
        order: [[`registrationNumber`, `desc`]],
        ajax: {
            url: Routing.generate(`vehicle_api`, true),
            type: `POST`
        },
        columns: [
            {data: `actions`, title: ``, className: `noVis`, orderable: false},
            {data: `registrationNumber`, title: `Immatriculation`},
            {data: `deliverer`, title: `Livreur`},
            {data: `locations`, title: `Emplacements`, orderable: false},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        drawConfig: {
            needsSearchOverride: true
        }
    });
}
