let tableDispatches = null;

$(function() {
    initPage();

    const filtersContainer = $('.filters-container');

    Select2Old.init(filtersContainer.find('.filter-select2[name="carriers"]'), 'Transporteurs');
    Select2Old.init(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), 'Urgences');
    Select2Old.dispatch(filtersContainer.find('.filter-select2[name="dispatchNumber"]'), 'Numéro de demande');
    Select2Old.init(filtersContainer.find('.filter-select2[name="multipleTypes"]'), 'Types');
    Select2Old.initFree(filtersContainer.find('.filter-select2[name="commandList"]'), $('#translateCommandNumber').val());
    Select2Old.user(filtersContainer.find('.ajax-autocomplete-user[name=receivers]'), 'Destinataires');
    Select2Old.user(filtersContainer.find('.ajax-autocomplete-user[name=requesters]'), 'Demandeurs');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DISPATCHES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
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
