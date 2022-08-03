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

    $('.filter-status-multiple-dropdown .dropdown-item:not(:first-of-type)').on('click', event => {
        const $clicked = $(event.target);console.log(event.target);
        if(!$clicked.is(`input[type="checkbox"]`)) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const $checkbox = $(event.currentTarget).find(`input[type="checkbox"]`);
            $checkbox.prop(`checked`, !$checkbox.is(`:checked`));

            if (!$checkbox.is(`:checked`)) {
                $(`.filter-status-multiple-dropdown input[type="checkbox"][name="all"]`).prop(`checked`, false);
            }
        }

        const $checkedCheckboxesLength = $(`.filter-status-multiple-dropdown .dropdown-item:not(.d-none) input[type=checkbox]:checked`).length;
        updateSelectedStatusesCount($checkedCheckboxesLength);
    });
});

function onFilterTypeChange($select) {
    let typesIds = $select.val();
    if(Array.isArray(typesIds)) {
        typesIds = [typesIds];
    }

    $('.statuses-filter').find('.dropdown-item').each(function() {
        const type = $(this).data('type');
        const typeLabel = $(this).data('type-label');
        const $input = $(this).find('input');
        if($input.attr('name') !== 'all') {
            if(typesIds.length > 0 && !typesIds.includes(type) && !typesIds.includes(typeLabel)) {
                $(this).addClass('d-none');
                $input.prop('checked', false);
            } else {
                $(this).removeClass('d-none');
            }
        }
    });

    if(!$select.data('first-load')) {
        const $checkboxes = $('.statuses-filter .filter-status-multiple-dropdown').find('input[type=checkbox]');
        $checkboxes.prop('checked', false);
        updateSelectedStatusesCount(0);
    }

    $select.data('first-load', false);
}

function checkAllInDropdown($checkbox) {
    const $parentMenu = $checkbox.parents('.dropdown-menu');
    const $checkboxes = $parentMenu.find(' .dropdown-item:not(.d-none) input[type=checkbox]:not(:first)');
    $checkboxes.each(function() {
        if(!$(this).parents('.dropdown-item').hasClass('d-none')) {
            $(this).prop('checked', $checkbox.is(':checked'));
        }
    });

    const checkboxesLength = $checkbox.is(':checked') ? $checkboxes.length : 0;
    updateSelectedStatusesCount(checkboxesLength);
}

function updateSelectedStatusesCount(length) {
    const plural = length > 1 ? 's' : '';
    $('.status-filter-title').html(`${length} statut${plural} sélectionné${plural}`);
}

function initNewHandlingEditor(modal) {
    Select2Old.location($('.ajax-autocomplete-location'));
    onTypeChange($(modal).find('select[name="type"]'));
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
                        'filterStatus': $('#filterStatus').val(),
                        'handlingIds': $('#handlingIds').val(),
                    },
                },
                hideColumnConfig: {
                    columns: [
                        {data: 'actions', name: 'actions', title: '', orderable: false},
                        ...columns,
                    ],
                    tableFilter: 'tableHandlings',
                },
                columns: [
                    {data: 'actions', name: 'actions', title: '', orderable: false},
                    ...columns,
                ],
            };
            return initDataTable('tableHandlings', tableHandlingConfig);
        });
}

function initModals(tableHandling) {
    let $modalNewHandling = $("#modalNewHandling");
    let $submitNewHandling = $("#submitNewHandling");
    let urlNewHandling = Routing.generate('handling_new', true);
    InitModal($modalNewHandling, $submitNewHandling, urlNewHandling, {tables: [tableHandling]});

    let $modalDeleteHandling = $('#modalDeleteHandling');
    let $submitDeleteHandling = $('#submitDeleteHandling');
    let urlDeleteHandling = Routing.generate('handling_delete', true);
    InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling, {tables: [tableHandling]});

    Select2Old.user($modalNewHandling.find('.ajax-autocomplete-user[name=receivers]'))
}
