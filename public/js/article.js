var pathArticle = Routing.generate('article_api', true);
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence' },
        { "data": 'Statut' },
        { "data": 'Libellé' },
        { "data": 'Référence article' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
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

var editorEditArticleAlreadyDone = false;
function initEditArticleEditor(modal) {
    if (!editorEditArticleAlreadyDone) {
        initEditor(modal);
        editorEditArticleAlreadyDone = true;
    }
};

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

function init() {
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
}
function initNewArticleEditor(modal) {
    initEditor(modal);
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
    let modalfooter =  $('#modalNewArticle').find('.modal-footer');
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
    data['referenceArticle'] = $('#referenceCEA').val();
    data['fournisseur'] = $('#fournisseur').val();
    $articleFourn.html('')
    modalfooter.addClass('d-none')
    if (data['referenceArticle'] && data['fournisseur']) {
        json = JSON.stringify(data);
        xhttp.open("POST", path, true);
        xhttp.send(json);
    }
}
