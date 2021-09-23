let editorNewLivraisonAlreadyDone = false;

$(function () {
    $('.select2').select2();

    initDateTimePicker();
    Select2Old.init($('#statut'), 'Statuts');
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
    if (!editorNewLivraisonAlreadyDone) {
        initEditorInModal(modal);
        editorNewLivraisonAlreadyDone = true;
    }

    clearModal(modal);
    Select2Old.location($('.ajax-autocomplete-location'));
    const type = ($('#modalNewDemande select[name="type"] option:selected').val());
    const $locationSelector = $(`#modalNewDemande select[name="destination"]`);

    if(!type) {
        $locationSelector.prop(`disabled`, true);
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
    let pathDemande = Routing.generate('demande_api', true);
    let tableDemandeConfig = {
        serverSide: true,
        processing: true,
        order: [['Date', 'desc']],
        ajax: {
            "url": pathDemande,
            "type": "POST",
            'data' : {
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
        columns: [
            {data: 'Actions', name: 'Actions', title: '', className: 'noVis', orderable: false, width: '10px'},
            {data: 'pairing', name: '', title: '', className: 'pairing-row', orderable: false},
            {data: 'Date', name: 'Date', title: 'Date'},
            {data: 'Demandeur', name: 'Demandeur', title: 'Demandeur'},
            {data: 'Numéro', name: 'Numéro', title: 'Numéro'},
            {data: 'Statut', name: 'Statut', title: 'Statut'},
            {data: 'Type', name: 'Type', title: 'Type'},
        ],
        columnDefs: [
            {
                type: "customDate",
                targets: 1
            }
        ],

    };

    const tableDemande = initDataTable('table_demande', tableDemandeConfig);

    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableDemande.column('Date:name').index();

            if (typeof indexDate === "undefined") return true;

            let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

            return (
                (dateMin == "" && dateMax == "")
                || (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
                || (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
                || (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
            );
        }
    );

    return tableDemande;
}
