let tableHandlings = null;

$(function() {
    $('.select2').select2();

    let params = GetRequestQuery();

    $(`.filters .submit-button`).on(`click`, () => params.date = null);

    initDatatable(params).then(table => {
        tableHandlings = table;

        initModals(tableHandlings);

        const $userFormat = $('#userDateFormat');
        const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

        initDateTimePicker('#dateMin, #dateMax, .date-cl', DATE_FORMATS_TO_DISPLAY[format]);
        initDatePickers();
        Select2Old.user($('.filter-select2[name="utilisateurs"]'), Translation.of('Demande', 'Général', 'Demandeurs', false));
        Select2Old.user($('.filter-select2[name="receivers"]'), Translation.of('Demande', 'Général', 'Destinataire(s)', false));
        Select2Old.init($('.filter-select2[name="emergencyMultiple"]'), Translation.of('Demande', 'Général', 'Urgence', false));

        // applique les filtres si pré-remplis
        let val = $('#filterStatus').val();

        if (params.date || val && val.length > 0) {
            if(val && val.length > 0) {
                let valuesStr = val.split(',');
                let valuesInt = [];
                valuesStr.forEach((value) => {
                    valuesInt.push(parseInt(value));
                })
                $('#statut').val(valuesInt).select2();
            }
        } else {
            // sinon, filtres enregistrés en base pour chaque utilisateur
            let path = Routing.generate('filter_get_by_page');
            let params = JSON.stringify(PAGE_HAND);
            $.post(path, params, function (data) {
                displayFiltersSup(data, true);
            }, 'json');
        }

        const $modalNewHandling = $("#modalNewHandling");
        $modalNewHandling.on('show.bs.modal', function () {
            initNewHandlingEditor("#modalNewHandling");
        });
    });

    initFilterStatusMutiple()
});

function initNewHandlingEditor(modal) {
    Select2Old.location($('.ajax-autocomplete-location'));
    onTypeChange($(modal).find('select[name="type"]'));
    initDatePickers();
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('handling_index');
    }
}

function initDatatable(params) {
    return $.post(Routing.generate('handling_api_columns'))
        .then((columns) => {
            let pathHandling = Routing.generate('handling_api', true);
            let tableHandlingConfig = {
                serverSide: true,
                processing: true,
                page: `handling`,
                order: [['creationDate', 'desc']],
                rowConfig: {
                    needsRowClickAction: true,
                    needsColor: true,
                    color: 'danger',
                    dataToCheck: 'emergency'
                },
                drawConfig: {
                    needsSearchOverride: true,
                },
                ajax: {
                    "url": pathHandling,
                    "type": "POST",
                    'data' : {
                        'filterStatus': $('#filterStatus').val(),
                        'selectedDate': () => params.date,
                    },
                },
                hideColumnConfig: {
                    columns: [
                        {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
                        ...columns,
                    ],
                    tableFilter: 'tableHandlings',
                },
                columns: [
                    {data: 'actions', name: 'actions', title: '', className: 'noVis', orderable: false},
                    ...columns,
                ],
            };
            return initDataTable('tableHandlings', tableHandlingConfig);
        });
}

function initModals(tableHandling) {
    let $modalNewHandling = $("#modalNewHandling");
    let $submitNewHandling = $("#submitNewHandling");
    let urlNewHandling = Routing.generate('handling_new', true);
    InitModal($modalNewHandling, $submitNewHandling, urlNewHandling, {
        tables: [tableHandling],
        keepModal: $modalNewHandling.is(`.keep-handling-modal-open`),
        success: () => $modalNewHandling.find(`.free-fields-container [data-type]`).addClass(`d-none`),
    });

    let $modalDeleteHandling = $('#modalDeleteHandling');
    let $submitDeleteHandling = $('#submitDeleteHandling');
    let urlDeleteHandling = Routing.generate('handling_delete', true);
    InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling, {tables: [tableHandling]});

    Select2Old.user($modalNewHandling.find('.ajax-autocomplete-user[name=receivers]'))
}
