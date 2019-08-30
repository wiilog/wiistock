$('.select2').select2();

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
        {"data": 'Nom'},
        {"data": 'Description'},
        {"data": 'Actions'}
    ],
    buttons: [
        'copy', 'excel', 'pdf'
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

function checkAndDeleteRow(icon) {
    let modalBody = modalDeleteEmplacement.find('.modal-body');
    let id = icon.data('id');
    let param = JSON.stringify(id);

    $.post(Routing.generate('emplacement_check_delete'), param, function (resp) {
        modalBody.html(resp.html);
        if (resp.delete == false) {
            submitDeleteEmplacement.hide();
        } else {
            submitDeleteEmplacement.show();
            submitDeleteEmplacement.attr('value', id);
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
        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $('.emplacement').find('.printButton').addClass('btn-disabled');
                $('.emplacement').find('.printButton').removeClass('btn-primary');
            } else {
                $('.emplacement').find('.printButton').removeClass('btn-disabled');
                $('.emplacement').find('.printButton').addClass('btn-primary');
            }
            tableEmplacement.search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $('.emplacement').find('.printButton').addClass('btn-disabled');
            $('.emplacement').find('.printButton').removeClass('btn-primary');
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
            $("#barcodes").empty();
            let i = 0;
            response.emplacements.forEach(function (code) {
                console.log(code);
                $('#barcodes').append('<img id="barcode' + i + '">')
                JsBarcode("#barcode" + i, code.replace('é', 'e').replace('è', 'e').trim(), {
                    format: "CODE128",
                });
                i++;
            });
            let doc = adjustScalesForDoc(response.tags);
            $("#barcodes").find('img').each(function () {
                doc.addImage($(this).attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
                doc.addPage();
            });
            doc.deletePage(doc.internal.getNumberOfPages());
            doc.save('Etiquettes-emplacements.pdf');
        }
    });
}

function printSingleArticleBarcode(button) {
    let params = {
        'emplacement': button.data('id')
    };
    $.post(Routing.generate('get_emplacement_from_id'), JSON.stringify(params), function (response) {
        if (response.exists) {
            $('#barcodes').append('<img id="singleBarcode">')
            JsBarcode("#singleBarcode", response.emplacementLabel, {
                format: "CODE128",
            });
            let doc = adjustScalesForDoc(response);
            doc.addImage($("#singleBarcode").attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
            doc.save('Etiquette concernant l\'emplacement ' + response.emplacementLabel + '.pdf');
            $("#singleBarcode").remove();
        } else {
            $('#cannotGenerate').click();
        }
    });
}