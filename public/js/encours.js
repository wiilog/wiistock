$(function () {
    initSelect2($('#emplacement'), 'Emplacements');
    initSelect2($('#natures'), 'Natures');

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
        ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    });
});

function loadPage() {
    let idLocationsToDisplay = $('#emplacement').val();
    let noFilter = (idLocationsToDisplay.length === 0);

    $('.block-encours').each(function () {
        const $blockEncours = $(this);
        const $tableEncours = $blockEncours.find('.encours-table');

        if (noFilter
            || (idLocationsToDisplay.indexOf($tableEncours.attr('id')) > -1)) {
            $blockEncours.removeClass('d-none');
            loadEncoursDatatable($tableEncours);
        }
        else {
            $blockEncours.addClass('d-none');
        }

    });
}

function loadEncoursDatatable($table) {
    const tableId = $table.attr('id');
    let tableAlreadyInit = $.fn.DataTable.isDataTable(`#${tableId}`);

    if (tableAlreadyInit) {
        $table.DataTable().ajax.reload();
    }
    else {
        let routeForApi = Routing.generate('en_cours_api', true);

        $table.DataTable({
            processing: true,
            responsive: true,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": routeForApi,
                "type": "POST",
                "data": {id: tableId}
            },
            columns: [
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Date de dépose'},
                {"data": 'delay', 'name': 'delay', 'title': 'Délai', render: (milliseconds, type) => renderMillisecondsToDelayDatatable(milliseconds, type)},
                {"data": 'late', 'name': 'late', 'title': 'late', 'visible': false, 'searchable': false},
            ],
            rowCallback: function (row, data) {
                $(row).addClass(data.late ? 'table-danger' : '');
            },
            "order": [[2, "desc"]]
        });
    }
}
