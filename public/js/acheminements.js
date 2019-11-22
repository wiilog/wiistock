let pathacheminements = Routing.generate('acheminements_api', true);
let tableAcheminements = $('#tableAcheminement').DataTable({
    serverSide: true,
    processing: true,
    order: [[0, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 0
        },
        {
            "orderable" : false,
            "targets" : 5
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathacheminements,
        "type": "POST",
    },
    columns: [
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'Emplacement prise', 'name': 'Emplacement prise', 'title': 'Emplacement prise' },
        { "data": 'Emplacement de dépose', 'name': 'Emplacement de dépose', 'title': 'Emplacement de dépose' },
        { "data": 'Nb Colis', 'name': 'Nb Colis', 'title': 'Nb Colis' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
    ],
});

let modalNewAcheminements = $("#modalNewAcheminements");
let submitNewAcheminements = $("#submitNewAcheminements");
let urlNewAcheminements = Routing.generate('acheminements_new', true);
InitialiserModal(modalNewAcheminements, submitNewAcheminements, urlNewAcheminements, tableAcheminements, (data) => printColis(data));

let modalModifyAcheminements = $('#modalEditAcheminements');
let submitModifyAcheminements = $('#submitEditAcheminements');
let urlModifyAcheminements = Routing.generate('acheminement_edit', true);
InitialiserModal(modalModifyAcheminements, submitModifyAcheminements, urlModifyAcheminements, tableAcheminements, (data) => printColis(data));

let modalDeleteAcheminements = $('#modalDeleteAcheminements');
let submitDeleteAcheminements = $('#submitDeleteAcheminements');
let urlDeleteAcheminements = Routing.generate('acheminement_delete', true);
InitialiserModal(modalDeleteAcheminements, submitDeleteAcheminements, urlDeleteAcheminements, tableAcheminements);

function printColis(data) {
    if (data.exists) {
        console.log(data);
        printBarcodes(data.codes, data, ('Colis d\'acheminements ' + data.acheminements + '.pdf'));
    } else {
        $('#cannotGenerate').click();
    }
}

function addInputColisClone(button)
{
    let $modal = button.closest('.modal-body');
    let $toClone = $modal.find('.inputColisClone').first();
    let $parent = $toClone.parent();
    $toClone.clone().appendTo($parent);

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