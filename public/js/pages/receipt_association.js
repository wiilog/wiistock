$(`.select2`).select2();

$(function () {
    const tableReceiptAssociation = initDatatable();

    initDateTimePicker();
    initModals(tableReceiptAssociation);
    Select2Old.user('Utilisateurs');

    let path = Routing.generate(`filter_get_by_page`);
    let params = JSON.stringify(PAGE_RECEIPT_ASSOCIATION);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, `json`);
});

function initDatatable() {
    let pathReceiptAssociation = Routing.generate(`receipt_association_api`, true);
    let tableReceiptAssociationConfig = {
        serverSide: true,
        processing: true,
        order: [[1, `desc`]],
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true
        },
        ajax: {
            url: pathReceiptAssociation,
            type: `POST`
        },
        columns: [
            {data: `Actions`, name: `Actions`, title: ``, className: `noVis`, orderable: false},
            {data: `creationDate`, name: `creationDate`, title: `Date`},
            {data: `pack`, name: `pack`, title: `Colis`},
            {data: `lastLocation`, name: `lastLocation`, title: `Dernier emplacement`},
            {data: `lastMovementDate`, name: `lastMovementDate`, title: `Date dernier mouvement`},
            {data: `receptionNumber`, name: `receptionNumber`, title: `réception.Réception`, translated: true},
            {data: `user`, name: `user`, title: `Utilisateur`},
        ],
    };
    return initDataTable(`receiptAssociationTable`, tableReceiptAssociationConfig)
}

function initModals(tableReceiptAssociation) {
    let modalNewReceiptAssociation = $(`#modalNewReceiptAssociation`);
    let submitNewReceiptAssociation = $(`#submitNewReceiptAssociation`);
    let urlNewReceiptAssociation = Routing.generate(`receipt_association_new`, true);
    InitModal(modalNewReceiptAssociation, submitNewReceiptAssociation, urlNewReceiptAssociation, {tables: [tableReceiptAssociation]});

    let modalDeleteReceiptAssociation = $(`#modalDeleteReceiptAssociation`);
    let submitDeleteReceiptAssociation = $(`#submitDeleteReceiptAssociation`);
    let urlDeleteReceiptAssociation = Routing.generate(`receipt_association_delete`, true);
    InitModal(modalDeleteReceiptAssociation, submitDeleteReceiptAssociation, urlDeleteReceiptAssociation, {tables: [tableReceiptAssociation]});
}
