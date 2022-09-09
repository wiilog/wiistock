let tableDispatches = null;

$(function() {
    initPage();

    const filtersContainer = $('.filters-container');

    Select2Old.init(filtersContainer.find('.filter-select2[name="carriers"]'), Translation.of('Demande', 'Acheminements', 'Divers', 'Transporteurs', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), Translation.of('Demande', 'Général','Urgences', false));
    Select2Old.dispatch(filtersContainer.find('.filter-select2[name="dispatchNumber"]'), Translation.of('Demande', 'Acheminements', 'Divers', 'N° demande', false));
    Select2Old.init(filtersContainer.find('.filter-select2[name="multipleTypes"]'), Translation.of('Demande', 'Acheminements', 'Divers', 'Types', false));
    Select2Old.initFree(filtersContainer.find('.filter-select2[name="commandList"]'), $('#translateCommandNumber').val());
    Select2Old.user(filtersContainer.find('.ajax-autocomplete-user[name=receivers]'), Translation.of('Demande', 'Général', 'Destinataire(s)', false));
    Select2Old.user(filtersContainer.find('.ajax-autocomplete-user[name=requesters]'), Translation.of('Demande', 'Général', 'Demandeurs', false));
    const $userFormat = $('#userDateFormat');
    const format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';

    initDateTimePicker('#dateMin, #dateMax', DATE_FORMATS_TO_DISPLAY[format]);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DISPATCHES);
    $.post(path, params, function(data) {
        displayFiltersSup(data, true);
    }, 'json');

    const $modalNewDispatch = $('#modalNewDispatch');
    $modalNewDispatch.on('show.bs.modal', function () {
        initNewDispatchEditor('#modalNewDispatch');
    });
});

function initPage() {
    return $
        .post(Routing.generate('dispatch_api_columns'))
        .then((columns) => {
            let tableDispatchesConfig = {
                serverSide: true,
                processing: true,
                order: [[1, "desc"]],
                ajax: {
                    "url": Routing.generate('dispatch_api', true),
                    "type": "POST",
                },
                rowConfig: {
                    needsRowClickAction: true,
                    needsColor: true,
                    color: 'danger',
                    dataToCheck: 'emergency'
                },
                drawConfig: {
                    needsSearchOverride: true,
                },
                columns,
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableDispatches'
                },
                page: 'dispatch'
            };

            tableDispatches = initDataTable('tableDispatches', tableDispatchesConfig);

            let $modalNewDispatch = $("#modalNewDispatch");
            let $submitNewDispatch = $("#submitNewDispatch");
            let urlDispatchNew = Routing.generate('dispatch_new', true);
            InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew, {tables: [tableDispatches]});
        });
}
