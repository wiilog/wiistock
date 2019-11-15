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
        {"data": 'lastMvtDate', 'name': 'lastMvtDate', 'title': 'Date dernier mouvement'},
        {"data": 'lastLocation', 'name': 'lastLocation', 'title': 'Dernier emplacement'},
        {"data": 'operator', 'name': 'operator', 'title': 'Opérateur'},
        {"data": 'actions', 'name': 'actions', 'title': 'Action'},
    ],
});
let tableHistoLitige;
function openTableHisto() {

    let pathHistoLitige = Routing.generate('histo_litige_api', {litige: $('#litigeId').val()}, true);
    tableHistoLitige = $('#tableHistoLitige').DataTable({
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

let modalNewLitige = $('#modalNewLitige');
let submitNewLitige = $('#submitNewLitige');
let urlNewLitige = Routing.generate('litige_new', {reloadArrivage: $('#arrivageId').val()}, true);
InitialiserModal(modalNewLitige, submitNewLitige, urlNewLitige, tableArrivageLitiges);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', {reloadArrivage: $('#arrivageId').val()}, true);
InitialiserModal(modalEditLitige, submitEditLitige, urlEditLitige, tableArrivageLitiges);

let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete', true);
InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableArrivageLitiges);

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
initModalEditArrivage(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, null, true, true);

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

function editRowLitige(button, afterLoadingEditModal = () => {}, arrivageId, litigeId) {
    let path = Routing.generate('litige_api_edit', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        litigeId: litigeId,
        arrivageId: arrivageId
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('#colisEditLitige').val(data.colis).select2();
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', litigeId);
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

function getCommentAndAddHisto()
{
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function (response) {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}

function initModalEditArrivage(modal, submit, path, callback, close, clear) {
    submit.click(function () {
        submitActionArrivage(modal, path, callback, close, clear);
    });
}

function submitActionArrivage(modal, path, callback, close, clear) {
    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let inputs = modal.find(".data");
    let Data = new FormData();
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    inputs.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        Data.append(name, val);
        // validation données obligatoires
        if ($(this).hasClass('needed') && (val === undefined || val === '' || val === null)) {
            let label = $(this).closest('.form-group').find('label').text();
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);
            $(this).addClass('is-invalid');
        }
        // validation valeur des inputs de type number
        if ($(this).attr('type') === 'number') {
            let val = parseInt($(this).val());
            let min = parseInt($(this).attr('min'));
            let max = parseInt($(this).attr('max'));
            if (val > max || val < min || isNaN(val)) {
                wrongNumberInputs.push($(this));
                $(this).addClass('is-invalid');
            }
        }
    });

    // ... et dans les checkboxes
    let checkboxes = modal.find('.checkbox');
    checkboxes.each(function () {
        Data.append([$(this).attr("name")], $(this).is(':checked'));
    });
    $("div[name='id']").each(function () {
        Data.append([$(this).attr("name")], $(this).attr('value'));
    });
    modal.find(".elem").remove();

    // ... puis on récupère les fichiers ...
    let files = $('#fileInput')[0].files;

    $.each(files, function(index, file) {
        Data.append('file' + index, file);
    });

    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
        if (close == true) modal.find('.close').click();

        $.ajax({
            url: path,
            data: Data,
            type: 'post',
            contentType: false,
            processData: false,
            cache: false,
            dataType: 'json',
            success: (data) => {
                if (data.redirect) {
                    window.location.href = data.redirect + '/1';
                    return;
                }
                // mise à jour des données d'en-tête après modification
                console.log(data.entete);
                if (data.entete) {
                    $('.zone-entete').html(data.entete)
                }

                if (clear) clearModal(modal);

                if (callback !== null) callback(data);
            }
        });
    } else {
        // ... sinon on construit les messages d'erreur
        let msg = '';

        // cas où il manque des champs obligatoires
        if (missingInputs.length > 0) {
            if (missingInputs.length == 1) {
                msg += 'Veuillez renseigner le champ ' + missingInputs[0] + ".<br>";
            } else {
                msg += 'Veuillez renseigner les champs : ' + missingInputs.join(', ') + ".<br>";
            }
        }
        // cas où les champs number ne respectent pas les valeurs imposées (min et max)
        if (wrongNumberInputs.length > 0) {
            wrongNumberInputs.forEach(function (elem) {
                let label = elem.closest('.form-group').find('label').text();

                msg += 'La valeur du champ ' + label;

                let min = elem.attr('min');
                let max = elem.attr('max');

                if (typeof (min) !== 'undefined' && typeof (max) !== 'undefined') {
                    if (min > max) {
                        msg += " doit être inférieure à " + max + ".<br>";
                    } else {
                        msg += ' doit être comprise entre ' + min + ' et ' + max + ".<br>";
                    }
                } else if (typeof (min) == 'undefined') {
                    msg += ' doit être inférieure à ' + max + ".<br>";
                } else if (typeof (max) == 'undefined') {
                    msg += ' doit être supérieure à ' + min + ".<br>";
                } else if (min < 1) {
                    msg += ' ne peut pas être rempli'
                }

            })
        }
        modal.find('.error-msg').html(msg);
    }
}