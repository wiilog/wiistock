$('.select2').select2();
let $printTag ;
let pathEmplacement = Routing.generate("emplacement_api", true);
let tableEmplacement = $('#tableEmplacement_id').DataTable({
    processing: true,
    serverSide: true,
    "lengthMenu": [10, 25, 50, 100, 1000],
    order: [[1, 'desc']],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathEmplacement,
        "type": "POST",
        'dataSrc': function (json) {
            $('#listEmplacementIdToPrint').val(json.listId);
            return json.data;
        }
    },
    'drawCallback': function () {
        overrideSearchEmplacement();
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'Nom', 'name': 'Nom', 'title': 'Nom'},
        {"data": 'Description', 'name': 'Description', 'title': 'Description'},
        {"data": 'Point de livraison', 'name': 'Point de livraison', 'title': 'Point de livraison'},
        {"data": 'Délai maximum', 'name': 'Délai maximum', 'title': 'Délai maximum'},
        {"data": 'Actif / Inactif', 'name': 'Actif / Inactif', 'title': 'Actif / Inactif'},
    ],
    buttons: [
        'copy', 'excel', 'pdf'
    ],
    columnDefs: [
        { "orderable": false, "targets": 5 },
        { "orderable": false, "targets": 0 }
    ]
});

let modalNewEmplacement = $("#modalNewEmplacement");
let submitNewEmplacement = $("#submitNewEmplacement");
let urlNewEmplacement = Routing.generate('emplacement_new', true);
InitialiserModal(modalNewEmplacement, submitNewEmplacement, urlNewEmplacement, tableEmplacement, (response) => displayErrorEmplacement($("#modalNewEmplacement"), response), false, false);

let modalDeleteEmplacement = $('#modalDeleteEmplacement');
let submitDeleteEmplacement = $('#submitDeleteEmplacement');
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true);
InitialiserModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, tableEmplacement);

let modalModifyEmplacement = $('#modalEditEmplacement');
let submitModifyEmplacement = $('#submitEditEmplacement');
let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
InitialiserModal(modalModifyEmplacement, submitModifyEmplacement, urlModifyEmplacement, tableEmplacement, (response) => displayErrorEmplacement($("#modalEditEmplacement"), response), false, false);

$(function () {
    $printTag = $('#btnPrint');
    managePrintButtonTooltip(true, $printTag);
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
        modal.find('.error-msg').html(response.message);
    } else {
        modal.find('.close').click();
    }
}

function overrideSearchEmplacement() {
    let $input = $('#tableEmplacement_id_filter input');
    $input.off();
    $input.on('keyup', function (e) {
        let $printButton = $('.emplacement').find('.printButton');
        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $printButton.addClass('btn-disabled');
                $printButton.removeClass('btn-primary');
                managePrintButtonTooltip(true, $printTag);
            } else {
                $printButton.removeClass('btn-disabled');
                $printButton.addClass('btn-primary');
                managePrintButtonTooltip(false, $printTag);
            }
            tableEmplacement.search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $printButton.addClass('btn-disabled');
            $printButton.removeClass('btn-primary');
            managePrintButtonTooltip(true, $printTag);
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function printLocationsBarCodes() {
    let path = Routing.generate('print_locations_bar_codes', {
        listEmplacements: $("#listEmplacementIdToPrint").val(),
        length: tableEmplacement.page.info().length,
        start: tableEmplacement.page.info().start
    }, true);
    window.open(path, '_blank');
}
