$('.select2').select2();
let pathEmplacement = Routing.generate("emplacement_api", true);
let tableEmplacementConfig = {
    processing: true,
    serverSide: true,
    "lengthMenu": [10, 25, 50, 100, 1000],
    order: [['name', 'desc']],
    ajax: {
        "url": pathEmplacement,
        "type": "POST",
        'dataSrc': function (json) {
            $('#listEmplacementIdToPrint').val(json.listId);
            return json.data;
        }
    },
    drawConfig: {
        needsEmplacementSearchOverride: true,
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    columns: [
        {"data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'name', 'name': 'name', 'title': 'Nom'},
        {"data": 'description', 'name': 'description', 'title': 'Description'},
        {"data": 'deliveryPoint', 'name': 'deliveryPoint', 'title': 'Point de livraison'},
        {"data": 'ongoingVisibleOnMobile', 'name': 'ongoingVisibleOnMobile', 'title': 'Encours visible'},
        {"data": 'maxDelay', 'name': 'maxDelay', 'title': 'Délai maximum'},
        {"data": 'active', 'name': 'active', 'title': 'Actif / Inactif'},
        {"data": 'allowedNatures', 'name': 'allowedNatures', 'title': 'natures.Natures de colis autorisées', translated: true, orderable: false},
    ]
};
let tableEmplacement = initDataTable('tableEmplacement_id', tableEmplacementConfig);

let $modalNewEmplacement = $("#modalNewEmplacement");
let $submitNewEmplacement = $("#submitNewEmplacement");
let urlNewEmplacement = Routing.generate('emplacement_new', true);
InitModal($modalNewEmplacement, $submitNewEmplacement, urlNewEmplacement, {tables: [tableEmplacement]});

let modalDeleteEmplacement = $('#modalDeleteEmplacement');
let submitDeleteEmplacement = $('#submitDeleteEmplacement');
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true);
InitModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, {tables: [tableEmplacement]});

let $modalModifyEmplacement = $('#modalEditEmplacement');
let $submitModifyEmplacement = $('#submitEditEmplacement');
let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
InitModal($modalModifyEmplacement, $submitModifyEmplacement, urlModifyEmplacement, {tables: [tableEmplacement]});

$(function () {
    const $printButton = $('#btnPrint');
    managePrintButtonTooltip(true, $printButton);
});

function checkAndDeleteRowEmplacement(icon) {
    let modalBody = modalDeleteEmplacement.find('.modal-body');
    let id = icon.data('id');
    let param = JSON.stringify(id);

    $.post(Routing.generate('emplacement_check_delete'), param, function (resp) {
        modalBody.html(resp.html);
        submitDeleteEmplacement.attr('value', id);

        if (resp.delete == false) {
            submitDeleteEmplacement.text('Désactiver');
        } else {
            submitDeleteEmplacement.text('Supprimer');
        }
    });
}

function printLocationsBarCodes($button, event) {
    if (!$button.hasClass('disabled')) {
        window.location.href = Routing.generate('print_locations_bar_codes', {
            listEmplacements: $("#listEmplacementIdToPrint").val()
        }, true);
    }
    else {
        event.stopPropagation();
    }
}
