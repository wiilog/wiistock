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
});

let pathMvt = Routing.generate('mvt_traca_api', true);
let tableMvt = $('#tableMvts').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#tableMvts_filter input'), tableMvt);
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "colis", 'name': 'colis', 'title': $('#colis').attr('placeholder')},
        {"data": 'location', 'name': 'location', 'title': 'Emplacement'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: 0
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

        if (
            (dateMin == "" && dateMax == "")
            ||
            (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
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

let editorNewMvtTracaAlreadyDone = false;

function initNewMvtTracaEditor(modalSelector) {
    const $modal = $(modalSelector);
    if (!editorNewMvtTracaAlreadyDone) {
        quillNew = initEditor(modalSelector + ' .editor-container-new');
        editorNewMvtTracaAlreadyDone = true;
    }
    $modal.find('.more-body-new-mvt-traca').addClass('d-none');

    initNewModalDate($modal);
    initNewModalOperator($modal);

    $.post(Routing.generate('mouvement_traca_get_appropriate_html'), JSON.stringify('fromStart'), function(response) {
        if (response.safran) {
            const $modalBody = $modal.find('.more-body-new-mvt-traca');
            $modalBody.html(response.modalBody);
            $modalBody.removeClass('d-none');
        }
        ajaxAutoCompleteEmplacementInit($modal.find('.ajax-autocompleteEmplacement'));

        $('.select2-colis').select2(({
            tags: true,
            "language": {
                "noResults": function () { return 'Ajoutez des éléments'; }
            },
        }))
    });
};

function initNewModalDate($modal) {
    const date = moment().format();
    $modal.find('.datetime').val(date.slice(0,16));
}

function initNewModalOperator($modal) {
    const $operatorSelect = $modal.find('.ajax-autocomplete-user');
    const $loggedUserInput = $modal.find('input[hidden][name="logged-user"]');
    ajaxAutoUserInit($operatorSelect, 'Opérateur');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect.val(null).trigger('change').append(option).trigger('change');
}

function switchMvtCreationType($input) {
    let pathToGetAppropriateHtml = Routing.generate("mouvement_traca_get_appropriate_html", true);
    let paramsToGetAppropriateHtml = $input.val();
    $.post(pathToGetAppropriateHtml, JSON.stringify(paramsToGetAppropriateHtml), function(response) {
        if (response) {
            $input.closest('.modal').find('.more-body-new-mvt-traca').html(response.modalBody);
            $input.closest('.modal').find('.new-mvt-common-body').removeClass('d-none');
            $input.closest('.modal').find('.more-body-new-mvt-traca').removeClass('d-none');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
            $('.select2-colis').select2(({
                tags: true,
                "language":{
                    "noResults" : function () { return 'Ajoutez des éléments'; }
                },
            }))
        }
    });
}
