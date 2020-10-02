let tableDispatches = null;

$(function() {
    initPage();

    const filtersContainer = $('.filters-container');

    initSelect2($('#statut'), 'Statuts');
    initSelect2(filtersContainer.find('.filter-select2[name="carriers"]'), 'Transporteurs');
    initSelect2(filtersContainer.find('.filter-select2[name="emergencyMultiple"]'), 'Urgences');
    ajaxAutoDispatchInit(filtersContainer.find('.filter-select2[name="dispatchNumber"]'), 'Numéro de demande');
    ajaxAutoUserInit(filtersContainer.find('.ajax-autocomplete-user[name=receivers]'), 'Destinataires');
    ajaxAutoUserInit(filtersContainer.find('.ajax-autocomplete-user[name=requesters]'), 'Demandeurs');
    initSelect2(filtersContainer.find('.filter-select2[name="multipleTypes"]'), 'Types');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DISPATCHES);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
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
                columns: columns.map(function (column) {
                    return {
                        ...column,
                        class: column.title === 'Actions' ? 'noVis' : undefined,
                        title: column.title === 'Actions' ? '' : column.title
                    }
                }),
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableDispatches'
                }
            };

            tableDispatches = initDataTable('tableDispatches', tableDispatchesConfig);

            let $modalNewDispatch = $("#modalNewDispatch");
            let $submitNewDispatch = $("#submitNewDispatch");
            let urlDispatchNew = Routing.generate('dispatch_new', true);
            InitModal($modalNewDispatch, $submitNewDispatch, urlDispatchNew, {tables: [tableDispatches]});

            let modalColumnVisible = $('#modalColumnVisibleDispatch');
            let submitColumnVisible = $('#submitColumnVisibleDispatch');
            let urlColumnVisible = Routing.generate('save_column_visible_for_dispatch', true);
            InitModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);
        });
}
