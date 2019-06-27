let pathArticle = Routing.generate('article_api', true);

let tableArticle = $('#tableArticle_id').DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence', 'name': 'Référence' },
        { "data": "Statut", 'name': 'Statut' },
        { "data": 'Libellé', 'name': 'Libellé' },
        { "data": 'Référence article', 'name': 'Référence article' },
        { "data": 'Quantité', 'name': 'Quantité' },
        { "data": 'Actions', 'name': 'Actions' }
    ],
});

$(document).ready(function () {
    let statutVisible = $(".statutVisible").val();
    if (!statutVisible) {
        tableArticle.column('Statut:name').visible(false);
    }
});

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('article_edit', true);
InitialiserModalArticle(modalEditArticle, submitEditArticle, urlEditArticle);

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let urlNewArticle = Routing.generate('article_new', true);
InitialiserModalArticle(modalNewArticle, submitNewArticle, urlNewArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('article_delete', true);
InitialiserModalArticle(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle);

// var editorEditArticleAlreadyDone = false;
// function initEditArticleEditor(modal) {
//     if (!editorEditArticleAlreadyDone) {
//         initEditorInModal(modal);
//         editorEditArticleAlreadyDone = true;
//     }
// };

let resetNewArticle = function (element) {
    element.removeClass('d-block');
    element.addClass('d-none');
}

function InitialiserModalArticle(modal, submit, path, callback = function () { }, close = true) {
    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText))
                data = JSON.parse(this.responseText);
                tableArticle.ajax.reload(function (json) {
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
            if ($(this).attr('type') === 'number') {
                let val = parseInt($(this).val());
                let min = parseInt($(this).attr('min'));
                let max = parseInt($(this).attr('max'));
                if (val > max || val < min) {
                    wrongInputs.push($(this));
                    $(this).addClass('is-invalid');
                }
            }
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

function init() {
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
}
function initNewArticleEditor(modal) {
    initEditor(modal + ' .editor-container-new');
};

function loadAndDisplayInfos(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_reference_by_fournisseur', true)
        let fournisseur = $(select).val();
        let params = JSON.stringify(fournisseur);

        $.post(path, params, function (data) {
            $('#newContent').html(data);
            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
            initNewArticleEditor("#modalNewArticle");
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
        })
    }
}

let getArticleFournisseur = function () {
    xhttp = new XMLHttpRequest();
    let $articleFourn = $('#newContent');
    let modalfooter = $('#modalNewArticle').find('.modal-footer');
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);

            if (data.content) {
                modalfooter.removeClass('d-none')
                $articleFourn.parent('div').addClass('d-block');
                $articleFourn.html(data.content);
                $('.error-msg').html('')
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
                initNewArticleEditor("#modalNewArticle");
            } else if (data.error) {
                $('.error-msg').html(data.error)
            }
        }
    }
    path = Routing.generate('ajax_article_new_content', true)
    let data = {};
    $('#newContent').html('');
    data['referenceArticle'] = $('#referenceCEA').val();
    data['fournisseur'] = $('#fournisseurID').val();
    $articleFourn.html('')
    modalfooter.addClass('d-none')
    if (data['referenceArticle'] && data['fournisseur']) {
        json = JSON.stringify(data);
        xhttp.open("POST", path, true);
        xhttp.send(json);
    }
}

let ajaxGetFournisseurByRefArticle = function (select) {
    let fournisseur = $('#fournisseur');
    let modalfooter = $('#modalNewArticle').find('.modal-footer');
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            if (data === false) {
                $('.error-msg').html('Vous ne pouvez par créer d\'article quand la référence CEA est gérée à la référence.');
            } else {
                fournisseur.removeClass('d-none');
                fournisseur.find('select').html(data);
                $('.error-msg').html('');
            }
        }
    }
    path = Routing.generate('ajax_fournisseur_by_refarticle', true)
    $('#newContent').html('');
    fournisseur.addClass('d-none');
    modalfooter.addClass('d-none')
    let refArticleId = select.val();
    let json = {};
    json['refArticle'] = refArticleId;
    Json = JSON.stringify(json);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
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
            let doc = adjustScalesForDoc(response);
            doc.addImage($("#singleBarcode").attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
            doc.save('Etiquette concernant l\'article ' + response.articleRef + '.pdf');
            $("#singleBarcode").remove();
        } else {
            $('#cannotGenerate').click();
        }
    });
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    $('#' + tog).prop('value', sel);

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

