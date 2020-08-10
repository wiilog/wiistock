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
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'Emplacement prise', 'name': 'Emplacement prise', 'title': 'Emplacement prise' },
        { "data": 'Emplacement de dépose', 'name': 'Emplacement de dépose', 'title': 'Emplacement de dépose' },
        { "data": 'Nb Colis', 'name': 'Nb Colis', 'title': 'Nb Colis' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
    ],
};
let tableAcheminements = initDataTable('tableAcheminement', tableAcheminementsConfig);

let modalNewAcheminements = $("#modalNewAcheminements");
let submitNewAcheminements = $("#submitNewAcheminements");
let urlNewAcheminements = Routing.generate('acheminements_new', true);
InitModal(modalNewAcheminements, submitNewAcheminements, urlNewAcheminements, {
    tables: [tableAcheminements],
    success: (data) => {
        printAcheminementFromId(data);
    }
});

let modalModifyAcheminements = $('#modalEditAcheminements');
let submitModifyAcheminements = $('#submitEditAcheminements');
let urlModifyAcheminements = Routing.generate('acheminement_edit', true);
InitModal(modalModifyAcheminements, submitModifyAcheminements, urlModifyAcheminements, {
    tables: [tableAcheminements],
    success: (data) => {
        printAcheminementFromId(data);
    }
});

let modalDeleteAcheminements = $('#modalDeleteAcheminements');
let submitDeleteAcheminements = $('#submitDeleteAcheminements');
let urlDeleteAcheminements = Routing.generate('acheminement_delete', true);
InitModal(modalDeleteAcheminements, submitDeleteAcheminements, urlDeleteAcheminements, {tables: [tableAcheminements]});

$(function() {
    initSelect2($('#statut'), 'Statuts');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ACHEMINEMENTS);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    let s = $("#s");
    if ($(button).hasClass('not-active')) {
        if (s.val() === "0") {
            s.val("1");
        } else {
            s.val("0");
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

