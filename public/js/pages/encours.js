fromDashboard = $('input[name=fromDashboard]').val();

$(function () {
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, Translation.of('Traçabilité', 'Encours', 'Emplacements', false), 1);
    Select2Old.init($('.filter-select2[name="natures"]'), Translation.of('Traçabilité', 'Encours', 'Natures', false));

    const isPreFilledFilter = $('.filters-container [name="isPreFilledFilter"]').val() === '1';

    $.post(Routing.generate('check_time_worked_is_defined', true), (data) => {
        if (data === false) {
            showBSAlert('Veuillez définir les horaires travaillés dans Paramétrage/Paramétrage global.', 'danger');
        }

        // filtres enregistrés en base pour chaque utilisateur
        getUserFiltersByPage(PAGE_ENCOURS, {preventPrefillFilters: isPreFilledFilter}, () => {
            extendsDateSort('date', 'YYYY-MM-DDTHH:mm:ss');
            loadPage();
        });
    });
});

function loadPage() {
    const idLocationsToDisplay = $('[name=emplacement]').val();
    const useTruckArrivals = $(`.filters-container input[name=useTruckArrivals]`).is(`:checked`) ? 1 : 0;
    const natures = $(`.filters-container [name=natures]`).val();

    const locationFiltersCounter = idLocationsToDisplay.length;
    const min = Number($('#encours-min-location-filter').val());

    if (locationFiltersCounter < min ) {
        $('.block-encours').addClass('d-none');
        showBSAlert(Translation.of('Traçabilité', 'Encours', 'Vous devez sélectionner au moins un emplacement dans les filtres'), 'danger')
    }
    else {
        $('.block-encours').each(function () {
            const $blockEncours = $(this);
            let $tableEncours = $blockEncours.find('.encours-table').filter(function() {
                return $(this).attr('id');
            });
            if (locationFiltersCounter === 0
                || (idLocationsToDisplay.indexOf($tableEncours.attr('id')) > -1)) {
                $blockEncours.removeClass('d-none');
                loadEncoursDatatable($tableEncours, useTruckArrivals, natures);
            } else {
                $blockEncours.addClass('d-none');
            }
        });
    }

    $.post(Routing.generate('check_location_delay', {locationIds: idLocationsToDisplay}, true), (response) => {
        if(!response.hasDelayError){
            showBSAlert(Translation.of('Traçabilité', 'Encours', 'Veuillez paramétrer le délai maximum de vos emplacements pour visualiser leurs encours.'), 'danger')
        }
    });
}

function loadEncoursDatatable($table, useTruckArrivals, natures) {
    const tableId = $table.attr('id');
    const data = {
        id: tableId,
        useTruckArrivals,
        natures,
    }

    let tableAlreadyInit = $.fn.DataTable.isDataTable(`#${tableId}`);
    extendsDateSort('customDate');
    if (tableAlreadyInit) {
        // modification des paramètres POST de la requête AJAX pour tenir compte du changement dans les filtres
        // settings() retourne les paramètres du datatable, ce qui permet de les modifier sans entièrement le redéfinir
        $table.DataTable().settings()[0].ajax.data = data;
        // rechargement du datatable avec les nouvelles données
        $table.DataTable().ajax.reload();
    }
    else {
        let routeForApi = Routing.generate('en_cours_api', {fromDashboard});
        let tableConfig = {
            processing: true,
            responsive: true,
            ajax: {
                "url": routeForApi,
                "type": "POST",
                data,
            },
            columns: [
                {data: 'linkedArrival', name: 'linkedArrival', className: 'noVis', orderable : false},
                {data: 'LU', name: 'LU', title: Translation.of('Traçabilité', 'Général', 'Unités logistiques')},
                {data: 'date', name: 'date', title: Translation.of('Traçabilité', 'Encours', 'Date de dépose') },
                {data: 'delay', name: 'delay', title: Translation.of('Traçabilité', 'Encours', 'Délai'), render: (milliseconds, type) => renderMillisecondsToDelay(milliseconds, type)},
                {data: 'late', name: 'late', title: 'late', 'visible': false, 'searchable': false},
            ],
            rowConfig: {
                needsColor: true,
                color: 'danger',
                dataToCheck: 'late',
                needsRowClickAction: true,
            },
            domConfig: {
                removeInfo: true,
            },
            order: [["delay", "desc"]],
            columnDefs: [
                {
                    type: "customDate",
                    targets: 2
                }
            ],
        };
        initDataTable(tableId, tableConfig);
    }
}
