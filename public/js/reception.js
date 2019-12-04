$('.select2').select2();
$('.select2-autocomplete-articles').select2({
    ajax: {
        url: Routing.generate('get_article_reception', {reception: $('#receptionId').val()}, true),
        dataType: 'json',
        delay: 250,
    },
    language: {
        searching: function () {
            return 'Recherche en cours...';
        },
        noResults: function () {
            return 'Aucun résultat.';
        }
    },
});

function loadFournisseurFilter() {
    $('#fournisseurFilter').select2({
        ajax: {
            url: Routing.generate('get_fournisseur_for_filter'),
            dataType: 'json',
            delay: 250,
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 1 caractère.';
            },
            searching: function () {
                return 'Recherche en cours...';
            },
            noResults: function () {
                return 'Aucun résultat.';
            }
        },
        minimumInputLength: 1,
        placeholder: 'Fournisseur'
    });
}
//RECEPTION
let path = Routing.generate('reception_api', true);
let table = $('#tableReception_id').DataTable({
    serverSide: true,
    processing: true,
    order: [[0, "desc"]],
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": path,
        "type": "POST"
    },
    columns: [
        { "data": 'Date', 'title': 'Date de création' },
        { "data": 'DateFin', 'title': 'Date de fin de réception' },
        { "data": 'Numéro de commande', 'title': 'Numéro de commande' },
        { "data": 'Fournisseur', 'title': 'Fournisseur' },
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Statut', 'title': 'Statut' },
        { "data": 'Commentaire', 'title': 'Commentaire'},
        { "data": 'Actions', 'title': 'Actions' }
    ],
});

let pathLitigesReception = Routing.generate('litige_reception_api', {reception: $('#receptionId').val()}, true);
let tableLitigesReception = $('#tableReceptionLitiges').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    scrollX: true,
    ajax: {
        "url": pathLitigesReception,
        "type": "POST",
    },
    columns: [
        {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'status', 'name': 'status', 'title': 'Statut'},
        {"data": 'lastHistoric', 'name': 'lastHistoric', 'title': 'Dernier historique'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
    ],
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 4,
            "visible": false
        }
    ],
    order: [
        [4, 'desc'],
    ],
});

function editRowLitige(button, afterLoadingEditModal = () => {}, receptionId, litigeId) {
    let path = Routing.generate('litige_api_edit_reception', true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        litigeId: litigeId,
        reception: receptionId
    };

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);
        modal.find('#colisEditLitige').select2({
            ajax: {
                url: Routing.generate('get_article_reception', {reception: $('#receptionId').val()}, true),
                dataType: 'json',
                delay: 250,
            },
            language: {
                searching: function () {
                    return 'Recherche en cours...';
                },
                noResults: function () {
                    return 'Aucun résultat.';
                }
            },
        });
        let values = [];
        data.colis.forEach(val => {
            values.push({
                id: val.id,
                text: val.text
            })
        });
        values.forEach(value => {
            $('#colisEditLitige').select2("trigger", "select", {
                data: value
            });
        });
        modal.find('#acheteursLitigeEdit').val(data.acheteurs).select2();
        afterLoadingEditModal()
    }, 'json');

    modal.find(submit).attr('value', litigeId);
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

let modalNewLitige = $('#modalNewLitige');
let submitNewLitige = $('#submitNewLitige');
let urlNewLitige = Routing.generate('litige_new_reception', true);
initModalWithAttachments(modalNewLitige, submitNewLitige, urlNewLitige, tableLitigesReception);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit_reception', true);
initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableLitigesReception);

let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete_reception', true);
InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableLitigesReception);

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateParts = a.split('/'),
            year = parseInt(dateParts[2]) - 1900,
            month = parseInt(dateParts[1]),
            day = parseInt(dateParts[0]);
        return Date.UTC(year, month, day, 0, 0, 0);
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Date de création:title').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;
        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

        ) {
            return true;
        }
        return false;
    }
);

let pathArticle = Routing.generate('article_by_reception_api', true);
function initDatatableConditionnement() {
    let tableFromArticle = $('#tableArticleInner_id').DataTable({
        info: false,
        paging: false,
        "language": {
            url: "/js/i18n/dataTableLanguage.json",
        },
        searching: false,
        destroy: true,
        ajax: {
            "url": pathArticle,
            "type": "POST",
            "data": function () {
                return {
                    'ligne': $('#ligneSelected').val()
                }
            },
        },
        columns: [
            { "data": 'Référence', 'name': 'Référence', 'title': 'Référence' },
            { "data": "Statut", 'name': 'Statut', 'title': 'Statut' },
            { "data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé' },
            { "data": 'Référence article', 'name': 'Référence article', 'title': 'Référence article' },
            { "data": 'Quantité', 'name': 'Quantité', 'title': 'Quantité' },
            { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' }
        ],
        aoColumnDefs: [{
            'sType': 'natural',
            'bSortable': true,
            'aTargets': [0]
        }]
    });

    let statutVisible = $("#statutVisible").val();
    if (!statutVisible) {
        tableFromArticle.column('Statut:name').visible(false);
    }

    initModalCondit(tableFromArticle);
}



$.extend($.fn.dataTableExt.oSort, {
    "natural-asc": function (a, b) {
        return parseInt(a) < parseInt(b) ? -1 : 1;
    },
    "natural-desc": function (a, b) {
        return parseInt(a) < parseInt(b) ? -1 : 1;
    }
});

function initModalCondit(tableFromArticle) {
    let modalEditInnerArticle = $("#modalEditArticle");
    let submitEditInnerArticle = $("#submitEditArticle");
    let urlEditInnerArticle = Routing.generate('article_edit', true);
    InitialiserModal(modalEditInnerArticle, submitEditInnerArticle, urlEditInnerArticle, tableFromArticle);

    let modalDeleteInnerArticle = $("#modalDeleteArticle");
    let submitDeleteInnerArticle = $("#submitDeleteArticle");
    let urlDeleteInnerArticle = Routing.generate('article_delete', true);
    InitialiserModal(modalDeleteInnerArticle, submitDeleteInnerArticle, urlDeleteInnerArticle, tableFromArticle);
}

let modalReceptionNew = $("#modalNewReception");
let SubmitNewReception = $("#submitReceptionButton");
let urlReceptionIndex = Routing.generate('reception_new', true)
InitialiserModal(modalReceptionNew, SubmitNewReception, urlReceptionIndex, table);

let ModalDelete = $("#modalDeleteReception");
let SubmitDelete = $("#submitDeleteReception");
let urlDeleteReception = Routing.generate('reception_delete', true)
InitialiserModal(ModalDelete, SubmitDelete, urlDeleteReception, table);

let modalModifyReception = $('#modalEditReception');
let submitModifyReception = $('#submitEditReception');
let urlModifyReception = Routing.generate('reception_edit', true);
InitialiserModal(modalModifyReception, submitModifyReception, urlModifyReception, table);


//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('reception_article_api', { 'id': id }, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence', 'title': 'Référence' },
        { "data": 'Libellé', 'title': 'Libellé' },
        { "data": 'Fournisseur', 'title': 'Fournisseur' },
        { "data": 'A recevoir', 'title': 'A recevoir' },
        { "data": 'Reçu', 'title': 'Reçu' },
        { "data": 'Actions', 'title': 'Actions' }
    ],
});

let modal = $("#modalAddLigneArticle");
let submit = $("#addArticleLigneSubmit");
let url = Routing.generate('reception_article_add', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalDeleteArticle = $("#modalDeleteLigneArticle");
let submitDeleteArticle = $("#submitDeleteLigneArticle");
let urlDeleteArticle = Routing.generate('reception_article_remove', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

let modalEditArticle = $("#modalEditLigneArticle");
let submitEditArticle = $("#submitEditLigneArticle");
let urlEditArticle = Routing.generate('reception_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

//GENERATOR BARCODE
let printBarcode = function (button) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let params = {
        'reception': button.data('id')
    };
    $.post(Routing.generate('get_article_refs'), JSON.stringify(params), function (response) {
        if (response.exists) {
            printBarcodes(response.refs, response, 'Etiquettes du ' + date + '.pdf', response.barcodeLabel);
        } else {
            $('#cannotGenerate').click();
        }
    });
}

//initialisation editeur de texte une seule fois
var editorNewReceptionAlreadyDone = false;
function initNewReceptionEditor(modal) {
    if (!editorNewReceptionAlreadyDone) {
        initEditorInModal(modal);
        editorNewReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'));
};

var editorEditReceptionAlreadyDone = false;
function initEditReceptionEditor(modal) {
    if (!editorEditReceptionAlreadyDone) {
        initEditorInModal(modal);
        editorEditReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur-edit'));
    ajaxAutoUserInit($('.ajax-autocomplete-user-edit'));
};

var editorNewArticleAlreadyDone = false;
function initNewArticleEditor(modal) {
    ajaxAutoRefArticleInit($('.ajax-autocomplete'));

    if (!editorNewArticleAlreadyDone) {
        initEditorInModal(modal);
        editorNewArticleAlreadyDone = true;
    }
};

var editorEditArticleAlreadyDone = false;
function initEditArticleEditor() {
    if (!editorEditArticleAlreadyDone) {
        initEditorInModal();
        editorEditArticleAlreadyDone = true;
    }
};


let getArticleFournisseur = function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            if (data.option) {
                let $articleFourn = $('#articleFournisseur');
                $articleFourn.parent('div').removeClass('d-none');
                $articleFourn.parent('div').addClass('d-block');
                $articleFourn.html(data.option);
            }
        }
    }
    path = Routing.generate('get_article_fournisseur', true)
    let data = {};
    data['referenceArticle'] = $('#reference').val();
    data['fournisseur'] = $('#fournisseurAddArticle').val();
    if (data['referenceArticle'] && data['fournisseur']) {
        json = JSON.stringify(data);
        xhttp.open("POST", path, true);
        xhttp.send(json);
    }
}

let resetNewArticle = function (element) {
    element.removeClass('d-block');
    element.addClass('d-none');
}

function addLot(button) {
    $.post(Routing.generate('add_lot'), function (response) {
        button.parent().append(response);
        $('#submitConditionnement').removeClass('d-none');
    });
}

function createArticleAndBarcodes(button, receptionId) {
    let data = {};
    data.refArticle = button.attr('data-ref');
    data.ligne = button.attr('data-id');
    data.quantiteLot = [];
    data.tailleLot = [];
    data.receptionId = receptionId;

    $('#modalChoose').find('input.data').each(function () {
        data[$(this).attr('name')].push($(this).val());
    });
    $.post(Routing.generate('validate_lot'), JSON.stringify(data), function (response) {
        let d = new Date();
        let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
        date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
        $('#modalChoose').find('.modal-choose').first().html('<span class="btn btn-primary" onclick="addLot($(this))"><i class="fa fa-plus"></i></span>');

        if (response.exists) {
            printBarcodes(response.refs, response,'Etiquettes du ' + date + '.pdf', response.barcodesLabel);
            tableArticle.ajax.reload(function (json) {
                if (this.responseText !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });
        } else {
            $('#cannotGenerateStock').click();
        }
    });
}

function printSingleBarcode(button) {
    let params = {
        'ligne': button.data('id')
    }
    $.post(Routing.generate('get_ligne_from_id'), JSON.stringify(params), function (response) {
        if (!response.article) {
            if (response.exists) {
                printBarcodes(
                    [response.ligneRef],
                    response,
                    'Etiquette concernant l\'article ' + response.ligneRef + '.pdf',
                    [response.barcodeLabel]
                );
            }
            else {
                $('#cannotGenerate').click();
            }
        } else {
            $('#ligneSelected').val(button.data('id'));
            $('#chooseConditionnement').click();
            let $submit = $('#submitConditionnement');
            $submit.attr('data-ref', response.article)
            $submit.attr('data-id', button.data('id'))
            initDatatableConditionnement();
            $submit.addClass('d-none');
        }
    });
}

function printSingleArticleBarcode(button) {
    let params = {
        'article': button.data('id')
    };
    $.post(Routing.generate('get_article_from_id'), JSON.stringify(params), function (response) {
        if (response.exists) {
            printBarcodes(
                [response.articleRef.barcode],
                response,
                'Etiquette concernant l\'article ' + response.articleRef.barcode + '.pdf',
                [response.articleRef.barcodeLabel]
            );
        }
        else {
            $('#cannotGenerate').click();
        }
    });
}

function checkIfQuantityArticle($select){
    let referenceId = $select.val();
    let path = Routing.generate('check_if_quantity_article');
    let params = JSON.stringify(referenceId);
    let $label = $('#label');

    if (referenceId) { // protection pour éviter appel ajax en cas vidage modale
        $.post(path, params, function(quantityByArticle){
            $label.removeClass('is-invalid');
            if(quantityByArticle) {
                $label.addClass('needed');
                $label.closest('div').find('label').html('Libellé*');
                $label.closest('.modal-body').find('#quantite').attr('disabled', true);
            } else {
                $label.removeClass('needed');
                $label.closest('div').find('label').html('Libellé');
                $label.closest('.modal-body').find('#quantite').attr('disabled', false);
            }
        });
    }

}

function finishReception(receptionId) {
    $.post(Routing.generate('reception_finish'), JSON.stringify(receptionId), function(data) {
        if (data === true) {
            window.location.href = Routing.generate('reception_index', true);
        } else {
            alertErrorMsg(data);
        }
    }, 'json');
}

$submitSearchReception = $('#submitSearchReception');

$submitSearchReception.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let fournisseur = $('#fournisseurFilter').val();
    let fournisseurString = fournisseur.toString();
    let fournisseurPiped = fournisseurString.split(',').join('|');
    saveFilters(PAGE_RECEPTION, dateMin, dateMax, statut, null, null, null, null, null, null, fournisseurPiped, function() {
        table
            .columns('Statut:name')
            .search(statut ? '^' + statut + '$' : '', true, false)
            .draw();

        table
            .columns('Fournisseur:name')
            .search(fournisseurPiped ? '^' + fournisseurPiped + '$' : '', true, false)
            .draw();

        table
            .draw();
    });
});

$(function() {
    loadFournisseurFilter();
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RECEPTION);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field === 'fournisseur') {
                let values = [];
                element.value.split(',').forEach(val => {
                    values.push({
                        id: val,
                        text: val
                    })
                });
                values.forEach(value => {
                    $('#fournisseurFilter').select2("trigger", "select", {
                        data: value
                    });
                });
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        let now = new Date();
        let day = ("0" + now.getDate()).slice(-2);
        let month = ("0" + (now.getMonth() + 1)).slice(-2);
        let today = now.getFullYear()+"-"+(month)+"-"+(day) ;
        if ($('#dateMax').val() === '') $('#dateMax').val(today);
        if ($('#dateMin').val() === '') $('#dateMin').val(today);
        $submitSearchReception.click();
    }, 'json');
});
