import Routing from '@app/fos-routing';
import {getUserFiltersByPage} from '@app/utils'
import AJAX, {POST} from "@app/ajax";

const fromDashboard = $('input[name=fromDashboard]').val();

global.loadPage = loadPage;

$(function () {
    Select2Old.location($('.ajax-autocomplete-emplacements'), {}, Translation.of('Traçabilité', 'Encours', 'Emplacements', false), 1);
    Select2Old.init($('.filter-select2[name="natures"]'), Translation.of('Traçabilité', 'Encours', 'Natures', false));

    const isPreFilledFilter = $('.filters-container [name="isPreFilledFilter"]').val() === '1';
    AJAX
        .route(
            POST,
            "check_time_worked_is_defined",
        )
        .json()
        .then(({result}) => {
            if (result === false) {
                showBSAlert('Veuillez définir les horaires travaillés dans Paramétrage/Paramétrage global.', 'danger');
            }

            // filtres enregistrés en base pour chaque utilisateur
            getUserFiltersByPage(PAGE_ENCOURS, {preventPrefillFilters: isPreFilledFilter}, () => {
                extendsDateSort('date', 'YYYY-MM-DDTHH:mm:ss');
                loadPage();
            });
        })


});

function loadPage() {
    const idLocationsToDisplay = $('[name=emplacement]').val();
    const useTruckArrivals = $(`.filters-container input[name=useTruckArrivals]`).is(`:checked`) ? 1 : 0;
    const natures = $(`.filters-container [name=natures]`).val();

    const locationFiltersCounter = idLocationsToDisplay.length;
    const min = Number($('#encours-min-location-filter').val());

    if (locationFiltersCounter < min) {
        $('.block-encours').addClass('d-none');
        showBSAlert(Translation.of('Traçabilité', 'Encours', 'Vous devez sélectionner au moins un emplacement dans les filtres'), 'danger')
    } else {
        $('.block-encours').each(function () {
            const $blockEncours = $(this);
            let $tableEncours = $blockEncours.find('.encours-table').filter(function () {
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

    AJAX
        .route(POST, 'check_location_delay')
        .json({
            locationIds: idLocationsToDisplay
        })
        .then((response) => {
            if (!response.hasDelayError) {
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
    };

    let tableAlreadyInit = $.fn.DataTable.isDataTable(`#${tableId}`);
    extendsDateSort('customDate');
    if (tableAlreadyInit) {
        // modification des paramètres POST de la requête AJAX pour tenir compte du changement dans les filtres
        // settings() retourne les paramètres du datatable, ce qui permet de les modifier sans entièrement le redéfinir
        $table.DataTable().settings()[0].ajax.data = data;
        // rechargement du datatable avec les nouvelles données
        $table.DataTable().ajax.reload();
    } else {
        const columns = $table.data('initial-visible').map((column) => {
            if (column.name === `delay`) {
                column.render = (milliseconds, type) => renderMillisecondsToDelay(milliseconds, type);
            }

            return column;
        });
        let routeForApi = Routing.generate('ongoing_pack_api', {fromDashboard});
        let tableConfig = {
            processing: true,
            responsive: true,
            ajax: {
                "url": routeForApi,
                "type": POST,
                data,
            },
            columns: [
                ...columns,
                {data: 'late', name: 'late', title: 'late', 'visible': false, 'searchable': false},
            ],
            rowConfig: {
                needsColor: true,
                color: 'danger',
                dataToCheck: 'late',
            },
            domConfig: {
                removeInfo: true,
            },
            order: [["delay", "desc"]],
            page: 'encours',
        };
        initDataTable(tableId, tableConfig);
    }
}
