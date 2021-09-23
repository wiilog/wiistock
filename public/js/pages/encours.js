fromDashboard = $('input[name=fromDashboard]').val();

$(function () {
    $('.filters-container').find('.submit-button').prop('disabled', fromDashboard);
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, "Emplacements", 1);
    Select2Old.init($('.filter-select2[name="natures"]'), 'Natures');

    const isPreFilledFilter = $('.filters-container [name="isPreFilledFilter"]').val() === '1';

    $.post(Routing.generate('check_time_worked_is_defined', true), (data) => {
        if (data === false) {
            showBSAlert('Veuillez définir les horaires travaillés dans Paramétrage/Paramétrage global.', 'danger');
        }

        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ENCOURS);
        $.post(path, params, function (data) {
            if (!isPreFilledFilter) {
                displayFiltersSup(data);
            }
            extendsDateSort('date');
            loadPage();
        }, 'json');
    });
});

function loadPage() {
    let idLocationsToDisplay = $('#emplacement').val();
    const locationFiltersCounter = idLocationsToDisplay.length;
    const min = Number($('#encours-min-location-filter').val());

    if (locationFiltersCounter < min ) {
        $('.block-encours').addClass('d-none');
        showBSAlert('Vous devez sélectionner au moins un emplacement dans les filtres', 'danger')
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
                loadEncoursDatatable($tableEncours);
            } else {
                $blockEncours.addClass('d-none');
            }
        });
    }
}

function loadEncoursDatatable($table) {
    const tableId = $table.attr('id');
    let tableAlreadyInit = $.fn.DataTable.isDataTable(`#${tableId}`);
    if (tableAlreadyInit) {
        $table.DataTable().ajax.reload();
    }
    else {
        let routeForApi = Routing.generate('en_cours_api', {fromDashboard: fromDashboard});
        let tableConfig = {
            processing: true,
            responsive: true,
            ajax: {
                "url": routeForApi,
                "type": "POST",
                "data": {id: tableId}
            },
            columns: [
                {"data": 'linkedArrival', 'name': 'linkedArrival', 'className': 'noVis', orderable : false},
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Date de dépose'},
                {"data": 'delay', 'name': 'delay', 'title': 'Délai', render: (milliseconds, type) => renderMillisecondsToDelay(milliseconds, type)},
                {"data": 'late', 'name': 'late', 'title': 'late', 'visible': false, 'searchable': false},
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
            order: [[2, "desc"]],
            columnDefs: [
                {
                    type: "date",
                    targets: 1
                }
            ],
        };
        initDataTable(tableId, tableConfig);
    }
}
