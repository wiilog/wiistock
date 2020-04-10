let path = Routing.generate('inv_anomalies_api', true);
let table = $('#tableAnomalies').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": path,
        "type": "POST"
    },
    rowCallback: function(row, data) {
        initActionOnRow(row);
    },
    columns:[
        { "data": 'Actions', 'title' : '', className: 'noVis', orderable: false, visible: false },
        { "data": 'reference', 'title' : 'Référence' },
        { "data": 'libelle', 'title' : 'Libellé' },
        { "data": 'quantite', 'title' : 'Quantité' },
    ],
});

function showModalAnomaly($button) {
    let ref = $button.data('ref');
    let isRef = $button.data('is-ref');
    let quantity = $button.data('quantity');
    let location = $button.data('location');
    let idEntry = $button.data('id-entry');

    let $modal = $('#modalTreatAnomaly');
    $modal.find('.ref-title').text(isRef ? 'Référence' : 'Article');
    $modal.find('.reference').val(ref);
    $modal.find('.isRef').val(isRef);
    $modal.find('.quantity').text(quantity);
    $modal.find('.location').text(location);
    $modal.find('.idEntry').val(idEntry);
}

let modalTreatAnomaly = $('#modalTreatAnomaly');
let submitTreatAnomaly = $('#submitTreatAnomaly');
let urlTreatAnomaly = Routing.generate('anomaly_treat', true);
InitialiserModal(modalTreatAnomaly, submitTreatAnomaly, urlTreatAnomaly, table, alertSuccessMsgAnomaly);


function alertSuccessMsgAnomaly(data)
{
    if (data) {
        alertSuccessMsg("L'anomalie a bien été traitée.");
    } else {
        alertSuccessMsg("Un mouvement de stock correctif vient d'être créé.");
    }
}
