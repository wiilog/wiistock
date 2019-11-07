const allowedExtensions = ['pdf', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'ppt', 'pptx', 'csv', 'txt'];

$('.select2').select2();

$(function () {

    //fill l'input acheteurs (modalNewLititge)
    let modal = $('#modalNewLitige');
    let inputAcheteurs = $('#acheteursLitigeHidden').val();
    let acheteurs = inputAcheteurs.split(',');
    acheteurs.forEach(value => {
        let option = new Option(value, value, false, false);
        modal.find('#acheteursLitige').append(option);
    });
    $('#acheteursLitige').val(acheteurs).select2();

    // ouvre la modale d'ajout de colis
    let addColis = $('#addColis').val();
    if (addColis) {
        $('#btnModalAddColis').click();
    }
});

function printLabels(data) {
    if (data.exists) {
        printBarcodes(data.codes, data, ('Colis arrivage ' + data.arrivage + '.pdf'));
    } else {
        $('#cannotGenerate').click();
    }
}

let pathColis = Routing.generate('colis_api', {arrivage: $('#arrivageId').val()}, true);
let tableColis = $('#tableColis').DataTable({
    responsive: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    scrollX: true,
    ajax: {
        "url": pathColis,
        "type": "POST"
    },
    columns: [
        {"data": 'code', 'name': 'code', 'title': 'Code'},
        {"data": 'deliveryDate', 'name': 'deliveryDate', 'title': 'Date dépose'},
        {"data": 'lastLocation', 'name': 'lastLocation', 'title': 'Dernier emplacement'},
        {"data": 'operator', 'name': 'operator', 'title': 'Opérateur'},
        {"data": 'actions', 'name': 'actions', 'title': 'Action'},
    ],
});

function openTableHisto() {

    let pathHistoLitige = Routing.generate('histo_litige_api', {litige: $('#litigeId').val()}, true);
    let tableHistoLitige = $('#tableHistoLitige').DataTable({
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        dom: '<"top">rt<"bottom"lp><"clear">'
    });
}


let modalAddColis = $('#modalAddColis');
let submitAddColis = $('#submitAddColis');
let urlAddColis = Routing.generate('arrivage_add_colis', true);
InitialiserModal(modalAddColis, submitAddColis, urlAddColis, tableColis, (data) => {
    printLabels(data);
    window.location.href = Routing.generate('arrivage_show', {id: $('#arrivageId').val()})
});

let pathArrivageLitiges = Routing.generate('arrivageLitiges_api', {arrivage: $('#arrivageId').val()}, true);
let tableArrivageLitiges = $('#tableArrivageLitiges').DataTable({
    responsive: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    scrollX: true,
    ajax: {
        "url": pathArrivageLitiges,
        "type": "POST"
    },
    columns: [
        {"data": 'firstDate', 'name': 'firstDate', 'title': 'Date de création'},
        {"data": 'status', 'name': 'status', 'title': 'Statut'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'updateDate', 'name': 'updateDate', 'title': 'Date de modification'},
        {"data": 'Actions', 'name': 'actions', 'title': 'Action'},
    ],
    order: [[0, 'desc']],
});

let editorNewLitigeAlreadyDone = false;
let quillNewLitige;

function initNewLitigeEditor(modal) {
    if (!editorNewLitigeAlreadyDone) {
        quillNewLitige = initEditor(modal + ' .editor-container-new');
        editorNewLitigeAlreadyDone = true;
    }
}

let editorEditLitigeAlreadyDone = false;
let quillEditLitige;

function initEditLitigeEditor(modal) {
    if (!editorEditLitigeAlreadyDone) {
        quillEditLitige = initEditor(modal + ' .editor-container-edit');
        editorEditLitigeAlreadyDone = true;
    }
}

let modalNewLitige = $('#modalNewLitige');
let submitNewLitige = $('#submitNewLitige');
let urlNewLitige = Routing.generate('litige_new', true);
InitialiserModal(modalNewLitige, submitNewLitige, urlNewLitige, tableArrivageLitiges);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', true);
InitialiserModal(modalEditLitige, submitEditLitige, urlEditLitige, tableArrivageLitiges);


function dragEnterDiv(event, div) {
    div.css('border', '3px dashed red');
}

function dragOverDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed red');
    return false;
};

function dragLeaveDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed #BBBBBB');
    return false;
}

function dropOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            // Stop the propagation of the event
            event.preventDefault();
            event.stopPropagation();
            div.css('border', '3px dashed green');

            let valid = checkFilesFormat(event.dataTransfer.files, div);

            if (valid) {
                upload(event.dataTransfer.files);
                clearErrorMsg(div);
            } else {
                div.css('border', '3px dashed #BBBBBB');
            }
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}

function dropNewOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            event.preventDefault();
            event.stopPropagation();
            div.css('border', '3px dashed green');

            let valid = checkFilesFormat(event.dataTransfer.files, div);

            if (valid) {
                keepForSave(event.dataTransfer.files);
                clearErrorMsg(div);
            }
            else div.css('border', '3px dashed #BBBBBB');
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}


function checkFilesFormat(files, div) {
    let valid = true;
    $.each(files, function (index, file) {
        if (file.name.includes('.') === false) {
            div.closest('.modal-body').next('.error-msg').html("Le format de votre pièce jointe n'est pas supporté. Le fichier doit avoir une extension.");
            valid = false;
        }
        // else if (!(allowedExtensions.includes(file.name.split('.').pop())) && valid) {
        //     div.closest('.modal-body').next('.error-msg').html('L\'extension .' + file.name.split('.').pop() + ' n\'est pas supportée.');
        //     valid = false;
        // }
    });
    return valid;
}

function openFE() {
    $('#fileInput').click();
}

function uploadFE(span) {
    let files = $('#fileInput')[0].files;
    let formData = new FormData();
    let div = span.closest('.dropFrame');
    clearErrorMsg(div);

    let valid = checkFilesFormat(files, div);

    if (valid) {
        $.each(files, function (index, file) {
            formData.append('file' + index, file);
        });
        let path = Routing.generate('litige_depose', true);

        let litigeId = $('#dropfile').data('litige-id');
        formData.append('id', litigeId);

        $.ajax({
            url: path,
            data: formData,
            type:"post",
            contentType:false,
            processData:false,
            cache:false,
            dataType:"json",
            success:function(html){
                let dropfile = $('#dropfile');
                dropfile.css('border', '3px dashed #BBBBBB');
                dropfile.after(html);
            }
        });
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
}

function openFENew() {
    $('#fileInputNew').click();
}

function uploadFENew(span) {
    let files = $('#fileInputNew')[0].files;
    let formData = new FormData();
    let div = span.closest('.dropFrame');
    clearErrorMsg(div);

    let valid = checkFilesFormat(files, div);

    if (valid) {
        $.each(files, function (index, file) {
            formData.append('file' + index, file);
        });
        let path = Routing.generate('garder_pj', true);
        $.ajax({
            url: path,
            data: formData,
            type: "post",
            contentType: false,
            processData: false,
            cache: false,
            dataType: "json",
            success: function (html) {
                let dropfile = $('#dropfileNew');
                dropfile.css('border', '3px dashed #BBBBBB');
                dropfile.after(html);
            }
        });
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
}

function keepForSave(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });

    let path = Routing.generate('garder_pj', true);

    $.ajax({
        url: path,
        data: formData,
        type:"post",
        contentType:false,
        processData:false,
        cache:false,
        dataType:"json",
        success:function(html){
            let dropfile = $('#dropfileNew');
            dropfile.css('border', '3px dashed #BBBBBB');
            dropfile.after(html);
        }
    });

}

function upload(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });
    let path = Routing.generate('litige_depose', true);

    let arrivageId = $('#dropfile').data('litige-id');
    formData.append('id', litigeId);

    $.ajax({
        url: path,
        data: formData,
        type: "post",
        contentType: false,
        processData: false,
        cache: false,
        dataType: "json",
        success: function (html) {
            let dropfile = $('#dropfile');
            dropfile.css('border', '3px dashed #BBBBBB');
            dropfile.after(html);
        }
    });
}

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
InitialiserModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, null, callbackEdit);

let modalDeleteArrivage = $('#modalDeleteArrivage');
let submitDeleteArrivage = $('#submitDeleteArrivage');
let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage);

function callbackEdit() {
    window.location.reload();
}

let quillEdit;
let originalText = '';

function editRowArrivage(button) {
    let path = Routing.generate('arrivage_edit_api', true);
    let modal = $('#modalEditArrivage');
    let submit = $('#submitEditArrivage');
    let id = button.data('id');
    let params = {id: id};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        quillEdit = initEditor('.editor-container-edit');
        modal.find('#acheteursEdit').val(data.acheteurs).select2();
        originalText = quillEdit.getText();
    }, 'json');

    modal.find(submit).attr('value', id);
}

function editRowLitige(button, afterLoadingEditModal = () => {}) {
    let path = Routing.generate('litige_api_edit', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');
    let id = button.data('id');
    let params = {id: id};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        quillEdit = initEditor('.editor-container-edit');
        modal.find('#colisEditLitige').val(data.colis).select2();
        originalText = quillEdit.getText();
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', id);
}

function deleteRowArrivage(button, modal, submit, hasLitige) {
    deleteRow(button, modal, submit);
    let hasLitigeText = modal.find('.hasLitige');
    if (hasLitige) {
        hasLitigeText.removeClass('d-none');
    } else {
        hasLitigeText.addClass('d-none');
    }
}

function deleteAttachementLitige(litigeId, originalName, pjName) {

    let path = Routing.generate('litige_delete_attachement');
    let params = {
        litigeId: litigeId,
        originalName: originalName,
        pjName: pjName
    };

    $.post(path, JSON.stringify(params), function (data) {
        let pjWithoutExtension = pjName.substr(0, pjName.indexOf('.'));
        if (data === true) {
            $('#' + pjWithoutExtension).remove();
        }
    });
}

function getDataAndPrintLabels(codes) {
    let path = Routing.generate('arrivage_get_data_to_print', true);
    let param = codes;

    $.post(path, JSON.stringify(param), function (response) {
        let codeColis = [];
        if (response.response.exists) {
            for(const code of response.codeColis) {
                codeColis.push(code.code)
            }
            printBarcodes(codeColis, response.response, ('Etiquettes.pdf'));
        }
    });
}

function printColisBarcode(codeColis) {
    let path = Routing.generate('get_print_data', true);

    $.post(path, function (response) {
        printBarcodes([codeColis], response, ('Etiquette colis ' + codeColis + '.pdf'));
    });
}

function deleteAttachementNew(pj) {
    let params = {
        pj: pj
    };
    $.post(Routing.generate('remove_one_kept_pj', true), JSON.stringify(params), function(data) {
        $('p.attachement').each(function() {
            if ($(this).attr('id') === pj) $(this).remove();
        });
    })
}
