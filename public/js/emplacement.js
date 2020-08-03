$('.select2').select2();
let pathEmplacement = Routing.generate("emplacement_api", true);
let tableEmplacementConfig = {
    processing: true,
    serverSide: true,
    "lengthMenu": [10, 25, 50, 100, 1000],
    order: [[1, 'desc']],
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
        {"data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Nom', 'name': 'Nom', 'title': 'Nom'},
        {"data": 'Description', 'name': 'Description', 'title': 'Description'},
        {"data": 'Point de livraison', 'name': 'Point de livraison', 'title': 'Point de livraison'},
        {"data": 'Délai maximum', 'name': 'Délai maximum', 'title': 'Délai maximum'},
        {"data": 'Actif / Inactif', 'name': 'Actif / Inactif', 'title': 'Actif / Inactif'},
        {"data": 'allowed-natures', 'name': 'allowed-natures', 'title': $('#allowedNaturePackTranslation').val(), orderable: false},
    ]
};
let tableEmplacement = initDataTable('tableEmplacement_id', tableEmplacementConfig);

let modalNewEmplacement = $("#modalNewEmplacement");
let submitNewEmplacement = $("#submitNewEmplacement");
let urlNewEmplacement = Routing.generate('emplacement_new', true);
InitialiserModal(modalNewEmplacement, submitNewEmplacement, urlNewEmplacement, tableEmplacement, (response) => displayErrorEmplacement($("#modalNewEmplacement"), response), true, false);

let modalDeleteEmplacement = $('#modalDeleteEmplacement');
let submitDeleteEmplacement = $('#submitDeleteEmplacement');
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true);
InitialiserModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, tableEmplacement);

let modalModifyEmplacement = $('#modalEditEmplacement');
let submitModifyEmplacement = $('#submitEditEmplacement');
let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
InitialiserModal(modalModifyEmplacement, submitModifyEmplacement, urlModifyEmplacement, tableEmplacement, (response) => displayErrorEmplacement($("#modalEditEmplacement"), response), true, false);

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

function displayErrorEmplacement($modal, response) {
    if (!response.success) {
        $modal.find('.error-msg').html(response.message);
    } else {
        $modal.find('.close').click();
    }
}

function printLocationsBarCodes($button, event) {
    if (!$button.hasClass('disabled')) {
        window.location.href = Routing.generate('print_locations_bar_codes', {
            listEmplacements: $("#listEmplacementIdToPrint").val(),
            length: tableEmplacement.page.info().length,
            start: tableEmplacement.page.info().start
        }, true);
    }
    else {
        event.stopPropagation();
    }
}
