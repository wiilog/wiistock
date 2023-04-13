let deliveryRequestTable = null;

$(function () {
    $('.select2').select2();

    initDateTimePicker();
    Select2Old.articleReference($('.ajax-autocomplete'));
    Select2Old.user('Utilisateurs');

    if (!$('#receptionFilter').val()) {
        // applique les filtres si pré-remplis
        let val = $('#filterStatus').val();
        if (val && val.length > 0) {
            let valuesStr = val.split(',');
            let valuesInt = [];
            valuesStr.forEach((value) => {
                valuesInt.push(parseInt(value));
            });
            $('#statut').val(valuesInt).select2();
        } else {
            // sinon, filtres enregistrés en base pour chaque utilisateur
            let path = Routing.generate('filter_get_by_page');
            let params = JSON.stringify(PAGE_DEM_LIVRAISON);
            $.post(path, params, function (data) {
                displayFiltersSup(data);
            }, 'json');
        }
    }

    let table = initPageDatatable();
    initPageModals(table);

    const $modalNewDemande = $('#modalNewDemande');
    $modalNewDemande.on('show.bs.modal', function () {
        initNewLivraisonEditor('#modalNewDemande');
    });
});

function initNewLivraisonEditor(modal) {
    clearModal(modal);
    Select2Old.location($('.ajax-autocomplete-location'));
    const type = ($('#modalNewDemande select[name="type"] option:selected').val());
    const $locationSelector = $(`#modalNewDemande select[name="destination"]`);
    const $demandeReceiver = $(`#modalNewDemande select[name="demandeReceiver"]`);

    if($demandeReceiver){
        $demandeReceiver.append($('input[name=receiverToDisplay]').val());
    }

    if(!type) {
        $('.free-fields-container').children().addClass('d-none');
        $locationSelector.prop(`disabled`, true);
    }

    const defaultTypeId = $('input[name=defaultTypeId]').val();
    if(defaultTypeId){
        $(`#modalNewDemande select[name="type"] option[value=${defaultTypeId}]`).prop('selected', true);
        toggleLocationSelect($('#modalNewDemande select[name="type"]'));
        onTypeChange($('#modalNewDemande select[name="type"]'));
    }
}

function onDeliveryTypeChange($type, mode) {
    toggleLocationSelect($type);
    toggleRequiredChampsLibres($type, mode);
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('demande_index');
    }
}

function initPageModals(tableDemande) {
    let urlNewDemande = Routing.generate('demande_new', true);
    let $modalNewDemande = $("#modalNewDemande");
    let $submitNewDemande = $("#submitNewDemande");
    InitModal($modalNewDemande, $submitNewDemande, urlNewDemande, {tables: tableDemande});
    onTypeChange($modalNewDemande.find('[name="type"]'));
}

function initPageDatatable() {
    const deliveryRequestPath = Routing.generate('demande_api', true);
    return $
        .get(Routing.generate('delivery_request_api_columns'))
        .then((columns) => {
            let deliveryRequestTableConfig = {
                serverSide: true,
                processing: true,
                order: [['createdAt', 'desc']],
                ajax: {
                    "url": deliveryRequestPath,
                    "type": "POST",
                    'data': {
                        'filterStatus': $('#filterStatus').val(),
                        'filterReception': $('#receptionFilter').val()
                    },
                },
                drawConfig: {
                    needsSearchOverride: true,
                },
                rowConfig: {
                    needsRowClickAction: true,
                },
                columns,
                hideColumnConfig: {
                    columns,
                    tableFilter: 'table_demande'
                },
                columnDefs: [
                    {
                        type: "customDate",
                        targets: 1
                    }
                ],
                page: 'deliveryRequest',
            };

            deliveryRequestTable = initDataTable('table_demande', deliveryRequestTableConfig);

            $.fn.dataTable.ext.search.push(
                function (settings, data) {
                    let dateMin = $('#dateMin').val();
                    let dateMax = $('#dateMax').val();
                    let indexDate = deliveryRequestTable.column('date:name').index();

                    if (typeof indexDate === "undefined") return true;

                    let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

                    return (
                        (dateMin === "" && dateMax === "")
                        || (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
                        || (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
                        || (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
                    );
                }
            );
        });
}
