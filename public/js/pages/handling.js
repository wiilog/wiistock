let tableHandlings = null;

$(function() {
    $('.select2').select2();

    initDatatable().then(table => {
        tableHandlings = table;

        initModals(tableHandlings);

        initDateTimePicker();
        Select2Old.user($('.filter-select2[name="utilisateurs"]'), 'Demandeurs');
        Select2Old.user($('.filter-select2[name="receivers"]'), 'Destinataires');
        Select2Old.init($('.filter-select2[name="emergencyMultiple"]'), 'Urgences');

        // applique les filtres si pré-remplis
        let val = $('#filterStatus').val();

        if (val && val.length > 0) {
            let valuesStr = val.split(',');
            let valuesInt = [];
            valuesStr.forEach((value) => {
                valuesInt.push(parseInt(value));
            })
            $('#statut').val(valuesInt).select2();
        } else {
            // sinon, filtres enregistrés en base pour chaque utilisateur
            let path = Routing.generate('filter_get_by_page');
            let params = JSON.stringify(PAGE_HAND);
            $.post(path, params, function (data) {
                displayFiltersSup(data);
            }, 'json');
        }

        const $modalNewHandling = $("#modalNewHandling");
        $modalNewHandling.on('show.bs.modal', function () {
            initNewHandlingEditor("#modalNewHandling");
        });
    });
});


function initNewHandlingEditor(modal) {
    Select2Old.location($('.ajax-autocomplete-location'));
    onTypeChange($(modal).find('select[name="type"]'));
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    let $statusHandling = $("#statusHandling");

    if ($(button).hasClass('not-active')) {
        if ($statusHandling.val() === "0") {
            $statusHandling.val("1");
        } else {
            $statusHandling.val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]')
        .not('[data-title="' + sel + '"]')
        .removeClass('active')
        .addClass('not-active');

    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]')
        .removeClass('not-active')
        .addClass('active');
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('handling_index');
    }
}

function initDatatable() {
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
                        'filterStatus': $('#filterStatus').val()
                    },
                },
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableHandlings',
                },
                columns,
            };
            return initDataTable('tableHandlings', tableHandlingConfig);
        });
}

function initModals(tableHandling) {
    let $modalNewHandling = $("#modalNewHandling");
    let $submitNewHandling = $("#submitNewHandling");
    let urlNewHandling = Routing.generate('handling_new', true);
    InitModal($modalNewHandling, $submitNewHandling, urlNewHandling, {tables: [tableHandling]});

    let $modalModifyHandling = $('#modalEditHandling');
    let $submitModifyHandling = $('#submitEditHandling');
    let urlModifyHandling = Routing.generate('handling_edit', true);
    InitModal($modalModifyHandling, $submitModifyHandling, urlModifyHandling, {tables: [tableHandling]});

    let $modalDeleteHandling = $('#modalDeleteHandling');
    let $submitDeleteHandling = $('#submitDeleteHandling');
    let urlDeleteHandling = Routing.generate('handling_delete', true);
    InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling, {tables: [tableHandling]});

    Select2Old.user($modalNewHandling.find('.ajax-autocomplete-user[name=receivers]'))
}
