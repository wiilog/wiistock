$(function () {
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacements", 3);
    initSelect2($('.filter-select2[name="natures"]'), 'Natures');

    $.post(Routing.generate('check_time_worked_is_defined', true), (data) => {
        if (data === false) {
            alertErrorMsg('Veuillez définir les horaires travaillés dans Paramétrage/Paramétrage global.', true);
        }

        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ENCOURS);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
            loadPage();
        }, 'json');
    });
});

function loadPage() {
    let idLocationsToDisplay = $('#emplacement').val();
    const $message = $('.error-msg');
    const locationFiltersCounter = idLocationsToDisplay.length;
    const min = Number($('#encours-min-location-filter').val());
    const max = Number($('#encours-max-location-filter').val());
    if (locationFiltersCounter < min || locationFiltersCounter > max) {
        $('.block-encours').addClass('d-none');
        $message.removeClass('d-none');
        if (locationFiltersCounter < min) {
            $message.text('Vous devez sélectionner au moins un emplacement dans les filtres')
        }
        else { // locationFiltersCounter > max
            $message.text(`Le nombre maximum d\'emplacements dans les filtres est de ${max}`)
        }
    }
    else {
        $message.addClass('d-none');
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
        let routeForApi = Routing.generate('en_cours_api', true);
        let tableConfig = {
            processing: true,
            responsive: true,
            ajax: {
                "url": routeForApi,
                "type": "POST",
                "data": {id: tableId}
            },
            columns: [
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Date de dépose'},
                {"data": 'delay', 'name': 'delay', 'title': 'Délai', render: (milliseconds, type) => renderMillisecondsToDelay(milliseconds, type)},
                {"data": 'late', 'name': 'late', 'title': 'late', 'visible': false, 'searchable': false},
            ],
            rowConfig: {
                needsColor: true,
                color: 'danger',
                dataToCheck: 'late'
            },
            domConfig: {
                removeInfo: true,
            },
            "order": [[2, "desc"]]
        };
        initDataTable(tableId, tableConfig);
    }
}
