let tableMvt;
let quillNew;

$(function () {
    $('.select2').select2();
    $('#modalNewMvtTraca').find('.list-multiple').select2();

    initDateTimePicker();
    initSelect2($('#statut'), 'Types');
    initSelect2($('#emplacement'), 'Emplacements');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_TRACA);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    initNewModal($('#modalNewMvtTraca'));

    return $
        .post(Routing.generate('tracking_movement_api_columns'))
        .then((columns) => {
            let config = {
                responsive: true,
                serverSide: true,
                processing: true,
                order: [[2, "desc"]],
                ajax: {
                    "url": Routing.generate('tracking_movement_api', true),
                    "type": "POST",
                },
                drawConfig: {
                    needsSearchOverride: true,
                },
                rowConfig: {
                    needsRowClickAction: true
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
                    tableFilter: 'tableMvts'
                },
                columnDefs: [
                    { "orderable": false, "targets": 0 },
                ],
            };

            tableMvt = initDataTable('tableMvts', config);

            let modalColumnVisible = $('#modalColumnVisibleTrackingMovement');
            let submitColumnVisible = $('#submitColumnVisibleTrackingMovement');
            let urlColumnVisible = Routing.generate('save_column_visible_for_tracking_movement', true);
            InitModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);
        });
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableMvt.column('date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

        return ((dateMin == "" && dateMax == "")
            || (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            || (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            || (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax)));
    }
);

let modalNewMvtTraca = $("#modalNewMvtTraca");
let submitNewMvtTraca = $("#submitNewMvtTraca");
let urlNewMvtTraca = Routing.generate('mvt_traca_new', true);
InitModal(
    modalNewMvtTraca,
    submitNewMvtTraca,
    urlNewMvtTraca,
    {
        tables: [tableMvt],
        keepModal: !Number($('#redirectAfterTrackingMovementCreation').val()),
        success: ({success, mouvementTracaCounter}) => {
            displayAlertModal(
                undefined,
                $('<div/>', {
                    class: 'text-center',
                    text: mouvementTracaCounter > 0
                        ? (mouvementTracaCounter > 1
                            ? 'Mouvements créés avec succès.'
                            : 'Mouvement créé avec succès.')
                        : 'Aucun mouvement créé.'
                }),
                [
                    {
                        class: 'btn btn-success m-0',
                        text: 'Continuer',
                        action: ($modal) => {
                            $modal.modal('hide')
                        }
                    }
                ],
                success ? 'success' : 'error'
            );
        }
    });

let $modalEditMvtTraca = $("#modalEditMvtTraca");
let $submitEditMvtTraca = $("#submitEditMvtTraca");
let urlEditMvtTraca = Routing.generate('mvt_traca_edit', true);
InitModal($modalEditMvtTraca, $submitEditMvtTraca, urlEditMvtTraca, {tables: [tableMvt]});

let $modalDeleteMvtTraca = $('#modalDeleteMvtTraca');
let $submitDeleteMvtTraca = $('#submitDeleteMvtTraca');
let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
InitModal($modalDeleteMvtTraca, $submitDeleteMvtTraca, urlDeleteArrivage, {tables: [tableMvt]});

function initNewModal($modal) {
    if (!quillNew) {
        quillNew = initEditor('#' + $modal.attr('id') + ' .editor-container-new');
    }

    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    ajaxAutoUserInit($operatorSelect, 'Opérateur');

    // Init mouvement fields if already loaded
    const $moreMassMvtContainer = $modal.find('.form-mass-mvt-container');
    if ($moreMassMvtContainer.length > 0) {
        const $emplacementPrise = $moreMassMvtContainer.find('.ajax-autocompleteEmplacement[name="emplacement-prise"]');
        const $emplacementDepose = $moreMassMvtContainer.find('.ajax-autocompleteEmplacement[name="emplacement-depose"]');
        const $colis = $moreMassMvtContainer.find('.select2-free[name="colis"]');
        ajaxAutoCompleteEmplacementInit($emplacementPrise, {autoSelect: true, $nextField: $colis});
        initFreeSelect2($colis);
        ajaxAutoCompleteEmplacementInit($emplacementDepose, {autoSelect: true});
    }
}

function resetNewModal($modal) {
    // date
    const date = moment().format();
    $modal.find('.datetime').val(date.slice(0, 16));

    // operator
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    const $loggedUserInput = $modal.find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');

    $modal.find('.more-body-new-mvt-traca').empty();

    // focus emplacementPrise if mass mouvement form is already loaded
    const $emplacementPrise = $modal.find('.ajax-autocompleteEmplacement[name="emplacement-prise"]');
    if ($modal.length > 0) {
        setTimeout(() => {
            $emplacementPrise.select2('open');
        }, 400);
    }
}

function switchMvtCreationType($input) {
    let pathToGetAppropriateHtml = Routing.generate("mouvement_traca_get_appropriate_html", true);
    let paramsToGetAppropriateHtml = $input.val();
    $.post(pathToGetAppropriateHtml, JSON.stringify(paramsToGetAppropriateHtml), function (response) {
        if (response) {
            const $modal = $input.closest('.modal');
            $modal.find('.more-body-new-mvt-traca').html(response.modalBody);
            $modal.find('.new-mvt-common-body').removeClass('d-none');
            $modal.find('.more-body-new-mvt-traca').removeClass('d-none');
            ajaxAutoCompleteEmplacementInit($modal.find('.ajax-autocompleteEmplacement'));
            initFreeSelect2($modal.find('.select2-free'));
        }
    });
}

/**
 * Used in mouvement_traca/index.html.twig
 */
function clearURL() {
    window.history.pushState({}, document.title, `${window.location.pathname}`);
}

