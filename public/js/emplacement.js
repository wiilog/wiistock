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
        { "data": 'Nom' },
        { "data": 'Description' },
        { "data": 'Actions' }
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

    $.post(Routing.generate('emplacement_check_delete'), param, function(resp) {
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
    $input.on('keyup', function(e){
        if (e.key === 'Enter'){
            tableEmplacement.search(this.value).draw();
            $('.emplacement').find('.printButton').removeClass('d-none');
        }  else if (e.key === 'Backspace') {
            $('.emplacement').find('.printButton').addClass('d-none');
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}
function getDataAndPrintLabels() {
    let path = Routing.generate('emplacement_get_data_to_print', true);
    let listEmplacements = $("#listEmplacementIdToPrint").val();
    let params = JSON.stringify({listEmplacements: listEmplacements});
    $.post(path, params, function (response) {
        if (response.tags.exists) {
            $("#barcodes").empty();
            let i = 0;
            response.emplacements.forEach(function(code) {
                $('#barcodes').append('<img id="barcode' + i + '">')
                JsBarcode("#barcode" + i, code, {
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