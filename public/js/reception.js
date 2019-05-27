$('.select2').select2();

//RECEPTION
let path = Routing.generate('reception_api', true);
let table = $('#tableReception_id').DataTable({
    order: [[1, "desc"]],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": path,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Fournisseur' },
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Actions' }
    ],
});

let pathArticle = Routing.generate('article_by_reception_api', true);

let initDataTableDone = false;
function initDatatableConditionnement() {
    if (!initDataTableDone) {
        var tableFromArticle = $('#tableArticleInner_id').DataTable({
            info: false,
            paging: false,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            searching: false,
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
                {"data": 'Référence', 'name': 'Référence', 'title': 'Référence'},
                {"data": "Statut", 'name': 'Statut', 'title': 'Statut'},
                {"data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé'},
                {"data": 'Référence article', 'name': 'Référence article', 'title': 'Référence article'},
                {"data": 'Quantité', 'name': 'Quantité', 'title': 'Quantité'},
                {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'}
            ],
        });

        let statutVisible = $("#statutVisible").val();
        console.log(statutVisible);
        if (!statutVisible) {
            tableFromArticle.column('Statut:name').visible(false);
        }
        initDataTableDone = true;
        initModalCondit(tableFromArticle);
    } else {
        $('#tableArticleInner_id').DataTable().ajax.reload();
    }
}

function initModalCondit(tableFromArticle) {
    let modalEditInnerArticle = $("#modalEditArticle");
    let submitEditInnerArticle = $("#submitEditArticle");
    let urlEditInnerArticle = Routing.generate('article_edit', true);
    InitialiserModalArticle(modalEditInnerArticle, submitEditInnerArticle, urlEditInnerArticle, tableFromArticle);

    let modalDeleteInnerArticle = $("#modalDeleteArticle");
    let submitDeleteInnerArticle = $("#submitDeleteArticle");
    let urlDeleteInnerArticle = Routing.generate('article_delete', true);
    InitialiserModalArticle(modalDeleteInnerArticle, submitDeleteInnerArticle, urlDeleteInnerArticle, tableFromArticle);
}

let modalReceptionNew = $("#modalNewReception");
let SubmitNewReception = $("#submitButton");
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
        { "data": 'Référence CEA', 'title': 'Référence CEA' },
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
    }
    $.post(Routing.generate('get_article_refs'), JSON.stringify(params), function (response) {
        if (response.exists) {
            $("#barcodes").empty();
            for (let i = 0; i < response.refs.length; i++) {
                $('#barcodes').append('<img id="barcode' + i + '">')
                JsBarcode("#barcode" + i, response.refs[i], {
                    format: "CODE128",
                });
            }
            let doc = new jsPDF('l', 'mm', [response.height, response.width]);
            $("#barcodes").find('img').each(function () {
                doc.addImage($(this).attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
                doc.addPage();
            });
            doc.deletePage(doc.internal.getNumberOfPages())
            doc.save('Etiquettes du ' + date + '.pdf');
        } else {
            $('#cannotGenerate').click();
        }
    });
}

let pathPrinterAll = Routing.generate('article_printer_all', { 'id': id }, true);
let printerAll = function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            data.forEach(function (element) {
                JsBarcode("#barcode", element, {
                    format: "CODE128",
                });

                printJS({
                    printable: 'barcode',
                    type: 'html',
                    maxWidth: 250
                });
            });
        }
    };
    Data = 'hello';
    json = JSON.stringify(Data);
    xhttp.open("POST", pathPrinterAll, true);
    xhttp.send(json);
}

function updateStock(select) {
    let id = select.val();
    $.post(Routing.generate('get_article_stock'), { 'id': id }, function (data) {
        $('#stock').val(data);
    }, "json");
}

//initialisation editeur de texte une seule fois
var editorNewReceptionAlreadyDone = false;
function initNewReceptionEditor(modal) {
    if (!editorNewReceptionAlreadyDone) {
        initEditor(modal);
        editorNewReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'));
};

var editorEditReceptionAlreadyDone = false;
function initEditReceptionEditor(modal) {
    if (!editorEditReceptionAlreadyDone) {
        initEditor(modal);
        editorEditReceptionAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur-edit'));
    ajaxAutoUserInit($('.ajax-autocomplete-user-edit'));
};

var editorNewArticleAlreadyDone = false;
function initNewArticleEditor(modal) {
    $('.ajax-autocomplete').select2({
        ajax: {
            url: Routing.generate('get_ref_articles'),
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
    });
    if (!editorNewArticleAlreadyDone) {
        initEditor(modal);
        editorNewArticleAlreadyDone = true;
    }
};

var editorEditArticleAlreadyDone = false;
function initEditArticleEditor() {
    if (!editorEditArticleAlreadyDone) {
        initEditor();
        editorEditArticleAlreadyDone = true;
    }
};

$('.ajax-autocomplete').select2({
    ajax: {
        url: Routing.generate('get_ref_articles'),
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
});

function ajaxGetArticle(select) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $('#newContent').html(data);
            $('#modalAddArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');

        }
    }
    path = Routing.generate('get_refArticle_in_reception', true)
    let data = {};
    data['referenceArticle'] = select.val();
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}


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
    data['referenceArticle'] = $('#referenceCEA').val();
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


function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}

function addLot(button) {
    $.post(Routing.generate('add_lot'), function (response) {
        button.parent().append(response);
    });
}

function createArticleAndBarcodes(button) {
    let data = {};
    data.refArticle = button.attr('data-ref');
    data.ligne = button.attr('data-id');
    data.quantiteLot = [];
    data.tailleLot = [];
    $('#modalChoose').find('input.data').each(function () {
        data[$(this).attr('name')].push($(this).val());
    });
    $.post(Routing.generate('validate_lot'), JSON.stringify(data), function (response) {
        let d = new Date();
        let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
        date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
        $('#modalChoose').find('.modal-choose').first().html('<span class="btn btn-primary" onclick="addLot($(this))"><i class="fa fa-plus"></i></span>');
        if (response.exists) {
            $("#barcodes").empty();
            for (let i = 0; i < response.refs.length; i++) {
                $('#barcodes').append('<img id="barcode' + i + '">')
                JsBarcode("#barcode" + i, response.refs[i], {
                    format: "CODE128",
                });
            }
            let doc = new jsPDF('l', 'mm', [response.height, response.width]);
            $("#barcodes").find('img').each(function () {
                doc.addImage($(this).attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
                doc.addPage();
            });
            doc.deletePage(doc.internal.getNumberOfPages())
            doc.save('Etiquettes du ' + date + '.pdf');
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
                $('#barcodes').append('<img id="singleBarcode">')
                JsBarcode("#singleBarcode", response.ligneRef, {
                    format: "CODE128",
                });
                let doc = new jsPDF('l', 'mm', [response.height, response.width]);
                doc.addImage($("#singleBarcode").attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
                doc.save('Etiquette concernant l\'article ' + response.ligneRef + '.pdf');
                $("#singleBarcode").remove();
            } else {
                $('#cannotGenerate').click();
            }
        } else {
            $('#ligneSelected').val(button.data('id'));
            // tableFromArticle.ajax.reload(function (json) {
            //     if (this.responseText !== undefined) {
            //         $('#myInput').val(json.lastInput);
            //     }
            // });
            $('#chooseConditionnement').click();
            let $submit = $('#submitConditionnement');
            $submit.attr('data-ref', response.article)
            $submit.attr('data-id', button.data('id'))
            initDatatableConditionnement();
        }
    });
}

function InitialiserModalArticle(modal, submit, path, table, callback = function () { }, close = true) {
    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText))
                data = JSON.parse(this.responseText);
                table.ajax.reload(function (json) {
                    if (this.responseText !== undefined) {
                        $('#myInput').val(json.lastInput);
                    }
                });
                callback(data);

                let inputs = modal.find('.modal-body').find(".data");
                // on vide tous les inputs
                inputs.each(function () {
                    $(this).val("");
                });
                // on remet toutes les checkboxes sur off
                let checkboxes = modal.find('.checkbox');
                checkboxes.each(function () {
                    $(this).prop('checked', false);
                })
            } else if (this.readyState == 4 && this.status == 250) {
                $('#cannotDeleteArticle').click();
            }
        };

        // On récupère toutes les données qui nous intéressent
        // dans les inputs...
        let inputs = modal.find(".data");
        let Data = {};
        let missingInputs = [];
        let wrongInputs = [];

        inputs.each(function () {
            let val = $(this).val();
            let name = $(this).attr("name");
            Data[name] = val;
            // validation données obligatoires
            if ($(this).hasClass('needed') && (val === undefined || val === '' || val === null)) {
                let label = $(this).closest('.form-group').find('label').text();
                missingInputs.push(label);
                $(this).addClass('is-invalid');
            }
            // validation valeur des inputs de type number
            // if ($(this).attr('type') === 'number') {
            //     let val = parseInt($(this).val());
            //     console.log(val)
            //     let min = parseInt($(this).attr('min'));
            //     console.log(min)
            //     let max = parseInt($(this).attr('max'));
            //     console.log(max)
            //     if (val > max || val < min) {
            //         wrongInputs.push($(this));
            //         $(this).addClass('is-invalid');
            //     }
            // }
        });

        // ... et dans les checkboxes
        let checkboxes = modal.find('.checkbox');
        checkboxes.each(function () {
            Data[$(this).attr("name")] = $(this).is(':checked');
        });
        // si tout va bien on envoie la requête ajax...
        if (missingInputs.length == 0 && wrongInputs.length == 0) {
            if (close == true) modal.find('.close').click();
            Json = {};
            Json = JSON.stringify(Data);
            xhttp.open("POST", path, true);
            xhttp.send(Json);
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
            if (wrongInputs.length > 0) {
                wrongInputs.forEach(function (elem) {
                    let label = elem.closest('.form-group').find('label').text();

                    msg += 'La valeur du champ ' + label;

                    let min = elem.attr('min');
                    let max = elem.attr('max');

                    if (typeof (min) !== 'undefined' && typeof (max) !== 'undefined') {
                        msg += ' doit être comprise entre ' + min + ' et ' + max + ".<br>";
                    } else if (typeof (min) == 'undefined') {
                        msg += ' doit être inférieure à ' + max + ".<br>";
                    } else if (typeof (max) == 'undefined') {
                        msg += ' doit être supérieure à ' + min + ".<br>";
                    }

                })
            }

            modal.find('.error-msg').html(msg);
        }
    });
}

function printSingleArticleBarcode(button) {
    let params = {
        'article': button.data('id')
    }
    $.post(Routing.generate('get_article_from_id'), JSON.stringify(params), function (response) {
        if (response.exists) {
            $('#barcodes').append('<img id="singleBarcode">')
            JsBarcode("#singleBarcode", response.articleRef, {
                format: "CODE128",
            });
            let doc = new jsPDF('l', 'mm', [response.height, response.width]);
            doc.addImage($("#singleBarcode").attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
            doc.save('Etiquette concernant l\'article ' + response.articleRef + '.pdf');
            $("#singleBarcode").remove();
        } else {
            $('#cannotGenerate').click();
        }
    });
}