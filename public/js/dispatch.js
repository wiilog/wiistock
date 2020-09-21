let tableDispatches = null;
let editorNewDispatchAlreadyDone = false;

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

function initNewDispatchEditor(modal) {
    if (!editorNewDispatchAlreadyDone) {
        initEditorInModal(modal);
        editorNewDispatchAlreadyDone = true;
    }
    clearModal(modal);
    ajaxAutoUserInit($(modal).find('.ajax-autocomplete-user'));
    ajaxAutoCompleteTransporteurInit($(modal).find('.ajax-autocomplete-transporteur'));

    const $operatorSelect = $(modal).find('.ajax-autocomplete-user').first();
    const $loggedUserInput = $(modal).find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement[name!=""]'));
}

function addInputColisClone(button)
{
    let $modal = button.closest('.modal-body');
    let $toClone = $modal.find('.inputColisClone').first();
    let $parent = $toClone.parent();
    $toClone.clone().appendTo($parent);
    $parent.children().last().find('.data-array').val('');
}

function onDispatchTypeChange($select) {
    toggleRequiredChampsLibres($select, 'create');
    typeChoice($select, '-new', $('#typeContentNew'))

    const type = parseInt($select.val());
    let $modalNewDispatch = $("#modalNewDispatch");
    const $selectStatus = $modalNewDispatch.find('select[name="statut"]');

    $selectStatus.removeAttr('disabled');
    $selectStatus.find('option[data-type-id="' + type + '"]').removeClass('d-none');
    $selectStatus.find('option[data-type-id!="' + type + '"]').addClass('d-none');
    $selectStatus.val(null).trigger('change');

    if($selectStatus.find('option:not(.d-none)').length === 0) {
        $selectStatus.siblings('.error-empty-status').removeClass('d-none');
        $selectStatus.addClass('d-none');
    } else {
        $selectStatus.siblings('.error-empty-status').addClass('d-none');
        $selectStatus.removeClass('d-none');

        const dispatchDefaultStatus = JSON.parse($selectStatus.siblings('input[name="dispatchDefaultStatus"]').val() || '{}');
        if (dispatchDefaultStatus[type]) {
            $selectStatus.val(dispatchDefaultStatus[type]);
        }
    }
}

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
                },
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
