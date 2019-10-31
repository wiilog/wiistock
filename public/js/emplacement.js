$('.select2').select2();
const PAGE_EMPLACEMENT = 'emplacement';

let pathEmplacement = Routing.generate("emplacement_api", true);
let tableEmplacement = $('#tableEmplacement_id').DataTable({
    processing: true,
    serverSide: true,
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
        overrideSearch();
    },
    columns: [
        {"data": 'Nom', 'name': 'Nom', 'title': 'Nom'},
        {"data": 'Description', 'name': 'Description', 'title': 'Description'},
        {"data": 'Point de livraison', 'name': 'Point de livraison', 'title': 'Point de livraison'},
        {"data": 'Actif / Inactif', 'name': 'Actif / Inactif', 'title': 'Actif / Inactif'},
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
    ],
    buttons: [
        'copy', 'excel', 'pdf'
    ],
    columnDefs: [
        { "orderable": false, "targets": 4 }
    ]
});

let modalNewEmplacement = $("#modalNewEmplacement");
let submitNewEmplacement = $("#submitNewEmplacement");
let urlNewEmplacement = Routing.generate('emplacement_new', true);
InitialiserModal(modalNewEmplacement, submitNewEmplacement, urlNewEmplacement, tableEmplacement, displayErrorEmplacement, false);

let modalDeleteEmplacement = $('#modalDeleteEmplacement');
let submitDeleteEmplacement = $('#submitDeleteEmplacement');
let urlDeleteEmplacement = Routing.generate('emplacement_delete', true);
InitialiserModal(modalDeleteEmplacement, submitDeleteEmplacement, urlDeleteEmplacement, tableEmplacement);

let modalModifyEmplacement = $('#modalEditEmplacement');
let submitModifyEmplacement = $('#submitEditEmplacement');
let urlModifyEmplacement = Routing.generate('emplacement_edit', true);
InitialiserModal(modalModifyEmplacement, submitModifyEmplacement, urlModifyEmplacement, tableEmplacement);

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

function displayErrorEmplacement(data) {
    let modal = $("#modalNewEmplacement");
    let msg = "Ce nom d'emplacement existe déjà. Veuillez en choisir un autre.";
    displayError(modal, msg, data);
}

function overrideSearch() {
    let $input = $('#tableEmplacement_id_filter input');
    $input.off();
    $input.on('keyup', function (e) {
        let $printButton = $('.emplacement').find('.printButton');
        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $printButton.addClass('btn-disabled');
                $printButton.removeClass('btn-primary');
            } else {
                $printButton.removeClass('btn-disabled');
                $printButton.addClass('btn-primary');
            }
            tableEmplacement.search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $printButton.addClass('btn-disabled');
            $printButton.removeClass('btn-primary');
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function getDataAndPrintLabels() {
    let path = Routing.generate('emplacement_get_data_to_print', true);
    let listEmplacements = $("#listEmplacementIdToPrint").val();
    let params = JSON.stringify({
        listEmplacements: listEmplacements,
        length: tableEmplacement.page.info().length,
        start: tableEmplacement.page.info().start
    });
    $.post(path, params, function (response) {
        if (response.tags.exists) {
            printBarcodes(response.emplacements, response.tags, 'Etiquettes-emplacements.pdf');
        }
    });
}

function printSingleArticleBarcode(button) {
    const params = {'emplacement': button.data('id')};
    $.post(Routing.generate('get_emplacement_from_id'), JSON.stringify(params), function (response) {
        if (response.exists) {
            printBarcodes(
                [response.emplacementLabel],
                response,
                'Etiquette concernant l\'emplacement ' + response.emplacementLabel + '.pdf'
            );
        }
        else {
            $('#cannotGenerate').click();
        }
    });
}

function reloadDatatableforActif() {
    let input = $('#actifInactif');
    let filterInput = input.prop('checked');
    input.attr('value', filterInput);
    let statut = filterInput;
    saveFiltersEmplacement(PAGE_EMPLACEMENT, statut);
}

function saveFiltersEmplacement(page, statut) {
    let path = Routing.generate('filter_sup_new');
    let params = {};
    if (statut !== null && statut !== undefined) {
        params.statut = statut;
    }
    params.page = page;

    $.post(path, JSON.stringify(params), function() {
        tableEmplacement.draw();
    }, 'json');
}