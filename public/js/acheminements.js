let pathacheminements = Routing.generate('acheminements_api', true);
let tableAcheminementsConfig = {
    serverSide: true,
    processing: true,
    order: [[1, "desc"]],
    columnDefs: [
        {
            "orderable" : false,
            "targets" : [0]
        }
    ],
    ajax: {
        "url": pathacheminements,
        "type": "POST",
    },
    rowConfig: {
        needsRowClickAction: true
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis' },
        { "data": 'Date', 'name': 'Date', 'title': 'Date demande' },
        { "data": 'Type', 'name': 'Type', 'title': 'Type' },
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'Emplacement prise', 'name': 'Emplacement prise', 'title': $('#takenLocationAcheminement').val() },
        { "data": 'Emplacement de dépose', 'name': 'Emplacement de dépose', 'title': $('#dropOffLocationAcheminement').val() },
        { "data": 'Nb Colis', 'name': 'Nb Colis', 'title': 'Nb Colis' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
    ],
};
let tableAcheminements = initDataTable('tableAcheminement', tableAcheminementsConfig);

let modalNewAcheminements = $("#modalNewAcheminements");
let submitNewAcheminements = $("#submitNewAcheminements");
let urlNewAcheminements = Routing.generate('acheminements_new', true);
initModalWithAttachments(modalNewAcheminements, submitNewAcheminements, urlNewAcheminements, tableAcheminements, printAcheminementFromId);

let modalModifyAcheminements = $('#modalEditAcheminements');
let submitModifyAcheminements = $('#submitEditAcheminements');
let urlModifyAcheminements = Routing.generate('acheminement_edit', true);
InitialiserModal(modalModifyAcheminements, submitModifyAcheminements, urlModifyAcheminements, tableAcheminements, printAcheminementFromId);

let modalDeleteAcheminements = $('#modalDeleteAcheminements');
let submitDeleteAcheminements = $('#submitDeleteAcheminements');
let urlDeleteAcheminements = Routing.generate('acheminement_delete', true);
InitialiserModal(modalDeleteAcheminements, submitDeleteAcheminements, urlDeleteAcheminements, tableAcheminements);

$(function() {
    initSelect2($('#statut'), 'Statuts');
    initSelect2($('#utilisateur'), 'Demandeur');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ACHEMINEMENTS);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});


let editorNewAcheminementAlreadyDone = false;

function initNewAcheminementEditor(modal) {
    if (!editorNewAcheminementAlreadyDone) {
        initEditorInModal(modal);
        editorNewAcheminementAlreadyDone = true;
    }
    clearModal(modal);
    ajaxAutoUserInit($('.ajax-autocomplete-user'));
    const $operatorSelect = $(modal).find('.ajax-autocomplete-user').first();
    const $loggedUserInput = $(modal).find('input[hidden][name="logged-user"]');
    let option = new Option($loggedUserInput.data('username'), $loggedUserInput.data('id'), true, true);
    $operatorSelect
        .val(null)
        .trigger('change')
        .append(option)
        .trigger('change');
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
}


function addInputColisClone(button)
{
    let $modal = button.closest('.modal-body');
    let $toClone = $modal.find('.inputColisClone').first();
    let $parent = $toClone.parent();
    $toClone.clone().appendTo($parent);
    $parent.children().last().find('.data-array').val('');
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    if ($(button).hasClass('not-active')) {
        if ($("#s").val() == "0") {
            $("#s").val("1");
        } else {
            $("#s").val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function printAcheminementFromId(data) {
    const $printButton = $(`#print-btn-acheminement-${data.acheminement}`);
    if ($printButton.length > 0) {
        window.location.href = $printButton.attr('href');
    }
}

