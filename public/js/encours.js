$(function () {
    initSelect2($('#emplacement'), 'Emplacements');
    initSelect2($('#natures'), 'Natures');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ENCOURS);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    $.post(Routing.generate('check_time_worked_is_defined', true), (data) => {
        if (data === false) {
            alertErrorMsg('Veuillez définir les horaires travaillés dans Paramétrage/Paramétrage global.', true);
        }
        initDatatables();
    });

});

function initDatatables() {
    let idLocationsToDisplay = $('#emplacement').val();
    let noFilter = idLocationsToDisplay.length === 0;

    $('.encours-table').each(function () {
        let that = $(this);
        if (idLocationsToDisplay.indexOf(that.attr('id')) < 0 && !noFilter) {
            that.closest('.block-encours').hide();
        } else {
            initOrReloadOneDatatable(that);
        }
    });
}

function initOrReloadOneDatatable(that) {
    let tableSelector = '#' + that.attr('id');
    let tableAlreadyInit = $.fn.DataTable.isDataTable(tableSelector);

    if (tableAlreadyInit) {
        $(tableSelector).DataTable().ajax.reload();
    } else {
        let routeForApi = Routing.generate('en_cours_api', true);

        that.DataTable({
            processing: true,
            responsive: true,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": routeForApi,
                "type": "POST",
                "data": {id: that.attr('id')}
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

function reloadDatatables() {
    let idLocationsToDisplay = $('#emplacement').val();
    let noFilter = idLocationsToDisplay.length === 0;

    $('.encours-table').each(function () {
        let that = $(this);
        let blockEncours = that.closest('.block-encours');

        if (idLocationsToDisplay.indexOf(that.attr('id')) < 0 && !noFilter) {
            blockEncours.hide();
        } else {
            blockEncours.show();
            initOrReloadOneDatatable(that);
        }
    });
}
