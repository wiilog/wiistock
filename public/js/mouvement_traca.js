
let quillNew;

$('.select2').select2();

$(function() {
    initDateTimePicker();
    initSelect2($('#statut'), 'Type');
    initSelect2($('#emplacement'), 'Emplacement');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_MVT_TRACA);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);
    initNewModal($('#modalNewMvtTraca'));
});

let pathMvt = Routing.generate('mvt_traca_api', true);
let tableMvt = $('#tableMvts').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[2, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#tableMvts_filter input'), tableMvt);
    },
    rowCallback: function(row, data) {
        initActionOnRow(row);
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis'},
        {"data": 'origin', 'name': 'origin', 'title': 'Issu de'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "colis", 'name': 'colis', 'title': $('#colis').attr('placeholder')},
        {"data": "reference", 'name': 'reference', 'title': 'Référence'},
        {"data": "label", 'name': 'label', 'title': 'Libellé'},
        {"data": 'location', 'name': 'location', 'title': 'Emplacement'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: [0, 1]
        }
    ],
    headerCallback: function(thead) {
        $(thead).find('th').eq(2).attr('title', "Colis");
    },
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
initModalWithAttachments(modalNewMvtTraca, submitNewMvtTraca, urlNewMvtTraca, tableMvt, ({success, mouvementTracaCounter}) => {
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
}, Number($('#redirectAfterTrackingMovementCreation').val()));

let modalEditMvtTraca = $("#modalEditMvtTraca");
let submitEditMvtTraca = $("#submitEditMvtTraca");
let urlEditMvtTraca = Routing.generate('mvt_traca_edit', true);
initModalWithAttachments(modalEditMvtTraca, submitEditMvtTraca, urlEditMvtTraca, tableMvt);

let modalDeleteArrivage = $('#modalDeleteMvtTraca');
let submitDeleteArrivage = $('#submitDeleteMvtTraca');
let urlDeleteArrivage = Routing.generate('mvt_traca_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

function initNewModal($modal) {
    if (!quillNew) {
        quillNew = initEditor('#' + $modal.attr('id') + ' .editor-container-new');
    }

    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    ajaxAutoUserInit($operatorSelect, 'Opérateur');

    // Init mouvement fields if already loaded
    const $moreContainer = $modal.find('.more-body-new-mvt-traca');
    const $moreMassMvtContainer = $moreContainer.find('.form-mass-mvt-container');
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
    $modal.find('.datetime').val(date.slice(0,16));

    // operator
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    const $loggedUserInput = $modal.find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');

    // focus emplacementPrise if mass mouvement form is already loaded
    const $moreContainer = $modal.find('.more-body-new-mvt-traca');
    const $moreMassMvtContainer = $moreContainer.find('.form-mass-mvt-container');
    if ($moreMassMvtContainer.length > 0) {
        setTimeout(() => {
            const $emplacementPrise = $moreMassMvtContainer.find('.ajax-autocompleteEmplacement[name="emplacement-prise"]');
            $emplacementPrise.select2('open');
        }, 400);
    }
}

function switchMvtCreationType($input) {
    let pathToGetAppropriateHtml = Routing.generate("mouvement_traca_get_appropriate_html", true);
    let paramsToGetAppropriateHtml = $input.val();
    $.post(pathToGetAppropriateHtml, JSON.stringify(paramsToGetAppropriateHtml), function(response) {
        if (response) {
            const $modal = $input.closest('.modal');
            $modal.find('.more-body-new-mvt-traca').html(response.modalBody);
            $modal.find('.new-mvt-common-body').removeClass('d-none');
            $modal.find('.more-body-new-mvt-traca').removeClass('d-none');
            ajaxAutoCompleteEmplacementInit($modal.find('.ajax-autocompleteEmplacement'));
            initFreeSelect2($('.select2-free'));
        }
    });
}
