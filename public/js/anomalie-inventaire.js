let path = Routing.generate('inv_anomalies_api', true);
let table = $('#tableAnomalies').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": path,
        "type": "POST"
    },
    columns:[
        { "data": 'reference', 'title' : 'Référence' },
        { "data": 'libelle', 'title' : 'Libellé' },
        { "data": 'quantite', 'title' : 'Quantité' },
        { "data": 'Actions', 'title' : 'Actions' },
    ],
});

function showModalAnomaly($button) {
    let ref = $button.data('ref');
    let isRef = $button.data('is-ref');
    let quantity = $button.data('quantity');
    let location = $button.data('location');

    let $modal = $('#modalTreatAnomaly');
    $modal.find('.ref-title').text(isRef ? 'Référence' : 'Article');
    $modal.find('.reference').val(ref);
    $modal.find('.isRef').val(isRef);
    $modal.find('.quantity').text(quantity);
    $modal.find('.location').text(location);

}

let modalTreatAnomaly = $('#modalTreatAnomaly');
let submitTreatAnomaly = $('#submitTreatAnomaly');
let urlTreatAnomaly = Routing.generate('anomaly_treat', true);
InitialiserModal(modalTreatAnomaly, submitTreatAnomaly, urlTreatAnomaly, table, alertSuccessMsgAnomaly);


function alertSuccessMsgAnomaly(data)
{
    if (data) {
        alertSuccessMsg("Un mouvement de stock correctif vient d'être créé.");
    }
}