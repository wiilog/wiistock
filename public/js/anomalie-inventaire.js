let path = Routing.generate('inv_anomalies_api', true);
let tableConfig = {
    ajax:{
        "url": path,
        "type": "POST"
    },
    rowConfig: {
        needsRowClickAction: true
    },
    columns:[
        { "data": 'Actions', 'title' : '', className: 'noVis', orderable: false },
        { "data": 'Ref', 'title' : 'Reférence article', 'name': 'reference' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'barCode', 'title' : 'Code barre' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
        { "data": 'Quantity', 'title' : 'Quantité' }
    ],
};
let table = initDataTable('tableAnomalies', tableConfig);

function showModalAnomaly($button) {
    let ref = $button.data('ref');
    let isRef = $button.data('is-ref');
    let barCode = $button.data('bar-code');
    let quantity = $button.data('quantity');
    let location = $button.data('location');
    let idEntry = $button.data('id-entry');

    let $modal = $('#modalTreatAnomaly');
    $modal.find('.ref-title').text(isRef ? 'Référence' : 'Article');
    $modal.find('.reference').text(ref);
    $modal.find('.barCode').val(barCode);
    $modal.find('.isRef').val(isRef);
    $modal.find('.quantity').text(quantity);
    $modal.find('.location').text(location);
    $modal.find('.idEntry').val(idEntry);
}

let modalTreatAnomaly = $('#modalTreatAnomaly');
let submitTreatAnomaly = $('#submitTreatAnomaly');
let urlTreatAnomaly = Routing.generate('anomaly_treat', true);
InitialiserModal(modalTreatAnomaly, submitTreatAnomaly, urlTreatAnomaly, table, alertSuccessMsgAnomaly);


function alertSuccessMsgAnomaly({success, message, quantitiesAreEqual}) {
    if (success) {
        if (quantitiesAreEqual) {
            alertSuccessMsg("L'anomalie a bien été traitée.");
        } else {
            alertSuccessMsg("Un mouvement de stock correctif vient d'être créé.");
        }
    }
    else {
        alertErrorMsg(message);
    }
}
