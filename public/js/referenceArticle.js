$('.select2').select2();

function InitialiserModalRefArticle(modal, submit, path, callback = function () { }, close = true) {
    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText))
                data = JSON.parse(this.responseText);
                if (data.new) {
                    tableRefArticle.row.add(data.new).draw(false);
                } else if (data.delete) {
                    tableRefArticle.row($('#delete' + data.delete).parents('div').parents('td').parents('tr')).remove().draw(false);
                } else if (data.edit) {
                    tableRefArticle.row($('#edit' + data.id).parents('div').parents('td').parents('tr')).remove().draw(false);
                    tableRefArticle.row.add(data.edit).draw(false);
                }
                callback(data);
                initRemove();

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
        let Data = {};
        let inputs = modal.find(".data");
        // Trouver les champs correspondants aux infos fournisseurs...
        let fournisseursWithRefAndLabel = [];
        let fournisseurReferences = modal.find('input[name="referenceFournisseur"]');
        let labelFournisseur = modal.find('input[name="labelFournisseur"]');
        let missingInputs = [];
        modal.find('select[name="fournisseur"]').each(function (index) {
            if ($(this).val()) {
                if (fournisseurReferences.eq(index).val() && labelFournisseur.eq(index).val()) {
                    fournisseursWithRefAndLabel.push($(this).val() + ';' + fournisseurReferences.eq(index).val() + ';' + labelFournisseur.eq(index).val());
                } else if (!fournisseurReferences.eq(index).val()) {
                    let label = fournisseurReferences.eq(index).closest('.form-group').find('label').text();
                    missingInputs.push(label);
                    fournisseurReferences.eq(index).addClass('is-invalid');
                } else if (!labelFournisseur.eq(index).val()) {
                    let label = labelFournisseur.eq(index).closest('.form-group').find('label').text();
                    missingInputs.push(label);
                    labelFournisseur.eq(index).addClass('is-invalid');
                }
            } else {
                let label = $(this).closest('.form-group').find('label').text();
                missingInputs.push(label);
                $(this).addClass('is-invalid');
            }
        });
        Data['frl'] = fournisseursWithRefAndLabel;
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
            // protection pour les cas où il y a des champs cachés
            if ($(this).attr('type') === 'number' && $(this).hasClass('needed')) {
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
            modal.find('.error-msg').html('');
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


let ModalRefArticleNew = $("#modalNewRefArticle");
let ButtonSubmitRefArticleNew = $("#submitNewRefArticle");
let urlRefArticleNew = Routing.generate('reference_article_new', true);
InitialiserModalRefArticle(ModalRefArticleNew, ButtonSubmitRefArticleNew, urlRefArticleNew, displayError, false);

let ModalDeleteRefArticle = $("#modalDeleteRefArticle");
let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
InitialiserModalRefArticle(ModalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle);

let modalModifyRefArticle = $('#modalEditRefArticle');
let submitModifyRefArticle = $('#submitEditRefArticle');
let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
InitialiserModalRefArticle(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle);

let modalPlusDemande = $('#modalPlusDemande');
let submitPlusDemande = $('#submitPlusDemande');
let urlPlusDemande = Routing.generate('plus_demande', true);
InitialiserModalRefArticle(modalPlusDemande, submitPlusDemande, urlPlusDemande);

let modalColumnVisible = $('#modalColumnVisible');
let submitColumnVisible = $('#submitColumnVisible');
let urlColumnVisible = Routing.generate('save_column_visible', true);
InitialiserModalRefArticle(modalColumnVisible, submitColumnVisible, urlColumnVisible);

let modalNewFilter = $('#modalNewFilter');
let submitNewFilter = $('#submitNewFilter');
let urlNewFilter = Routing.generate('filter_new', true);
InitialiserModalRefArticle(modalNewFilter, submitNewFilter, urlNewFilter, displayNewFilter);

let url = Routing.generate('ref_article_api', true);

$(document).ready(function () {
    $.post(Routing.generate('ref_article_api_columns'), function (columns) {
        tableRefArticle = $('#tableRefArticle_id').DataTable({
            processing: true,
            serverSide: true,
            sortable: false,
            ordering: false,
            paging: true,
            scrollX: true,
            order: [[1, 'asc']],
            ajax: {
                'url': url,
                'type': 'POST',
                'dataSrc': function (json) {
                    return json.data;
                }
            },
            'drawCallback': function () {
                loadSpinnerAR($('#spinner'));
                initRemove();
                hideColumnChampsLibres();
            },
            length: 10,
            columns: columns,
            language: {
                url: "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
            },
        });
    })
});

//COLUMN VISIBLE
let tableColumnVisible = $('#tableColumnVisible_id').DataTable({
    language: {
        url: "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    "paging": false,
    "info": false
});

function visibleColumn(check) {
    let columnNumber = check.data('column')
    let column = tableRefArticle.column(columnNumber);
    column.visible(!column.visible());
    let columnClass = $('#tableRefArticle_id').find('thead').find('th');
    columnClass.each(function () {
        $(this).removeClass('libre');
        $(this).addClass('fixe');
    })
    if (check.hasClass('data')) {
        check.removeClass('data');
    } else {
        check.addClass('data');
    }
}

function hideColumnChampsLibres() {
    tableRefArticle.columns('.libre').visible(false);
}



//Récupére Id du type selectionné
function idType(div, idInput) {
    let id = div.attr('value');
    $(idInput).attr('value', id);
}


function showDemande(bloc) {
    let $livraisonShow = $('#livraisonShow');
    let $collecteShow = $('#collecteShow');

    if (bloc.data("title") == "livraison") {
        $collecteShow.removeClass('d-block');
        $collecteShow.addClass('d-none');
        $collecteShow.find('div').find('select').removeClass('data');
        $collecteShow.find('div').find('.quantite').removeClass('data');
        $collecteShow.find('.data').removeClass('needed');

        $livraisonShow.removeClass('d-none');
        $livraisonShow.addClass('d-block');
        $livraisonShow.find('div').find('select').addClass('data');
        $livraisonShow.find('div').find('.quantite').addClass('data');
        $livraisonShow.find('.data').addClass('needed');

    } else if (bloc.data("title") == "collecte") {
        $collecteShow.removeClass('d-none');
        $collecteShow.addClass('d-block');
        $collecteShow.find('div').find('select').addClass('data')
        $collecteShow.find('div').find('.quantite').addClass('data')
        $collecteShow.find('.data').addClass('needed');

        $livraisonShow.removeClass('d-block');
        $livraisonShow.addClass('d-none');
        $livraisonShow.find('div').find('select').removeClass('data')
        $livraisonShow.find('div').find('.quantite').removeClass('data')
        $livraisonShow.find('.data').removeClass('needed');
    }
}


// affiche le filtre après ajout
function displayNewFilter(data) {
    $('#filters').append(data.filterHtml);
    tableRefArticle.clear();
    tableRefArticle.ajax.reload();
}

// suppression du filtre au clic dessus
function initRemove() {
    $('.filter').on('click', removeFilter);
}

function removeFilter() {
    $(this).remove();

    let params = JSON.stringify({ 'filterId': $(this).find('.filter-id').val() });
    $.post(Routing.generate('filter_delete', true), params, function () {
        tableRefArticle.clear();
        tableRefArticle.ajax.reload();
    });
}

// modale ajout d'un filtre, affichage du champ "contient" en fonction du champ sélectionné
function displayFilterValue(elem) {
    let type = elem.find(':selected').data('type');
    let modalBody = elem.closest('.modal-body');

    // cas particulier de liste déroulante pour type
    if (type == 'list') {
        $.getJSON(Routing.generate('type_show_select'), function (data) {
            modalBody.find('.input').html(data);
        })
    } else {
        if (type == 'booleen') type = 'checkbox';
        modalBody.find('.input').html('<input type="' + type + '" class="form-control data ' + type + '" id="value" name="value">');
    }


    let label = '';
    switch (type) {
        case 'checkbox':
            label = 'Oui / Non';
            break;
        case 'number':
        case 'list':
            label = 'Valeur';
            break;
        default:
            label = 'Contient';
    }

    elem.closest('.modal-body').find('.valueLabel').text(label);
}

function displayError(data) {
    let modal = $("#modalNewRefArticle");
    if (data === false) {
        let msg = 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.';
        modal.find('.error-msg').html(msg);
    } else {
        modal.find('.close').click();
    }
}

let recupIdRefArticle = function (div) {
    let id = div.data('id');
    $('#submitPlusDemande').val(id);
}

function ajaxPlusDemandeContent(button, demande) {
    $('.plusDemandeContent').html('');
    $('.editChampLibre').html('');
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            if (dataReponse.plusContent) {
                $('.plusDemandeContent').html(dataReponse.plusContent);
            } else {
                //TODO gérer erreur
            }
            if (dataReponse.editChampLibre) {
                $('.editChampLibre').html(dataReponse.editChampLibre);
            } else {
                //TODO gérer erreur
            }
            showDemande(button)
        }
    }
    let json = {
        'demande': demande,
        'id': $('#submitPlusDemande').val(),
    };
    let Json = JSON.stringify(json)
    let path = Routing.generate('ajax_plus_demande_content', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}

let ajaxEditArticle = function (select) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            if (dataReponse.editChampLibre) {
                $('.editChampLibre').html(dataReponse.editChampLibre);
                displayRequireChamp($('#typeEditArticle'), 'edit');
                // initEditor('.editor-container');
            } else {
                //TODO gérer erreur
            }
        }
    }
    let json = select.val();
    let path = Routing.generate('ajax_edit_article', true);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}



//initialisation editeur de texte une seule fois
var editorNewReferenceArticleAlreadyDone = false;
function initNewReferenceArticleEditor(modal) {
    if (!editorNewReferenceArticleAlreadyDone) {
        initEditor(modal);
        editorNewReferenceArticleAlreadyDone = true;
    }
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
};

var editorEditRefArticleAlreadyDone = false;
function initEditRefArticleEditor(modal) {
    if (!editorEditRefArticleAlreadyDone) {
        initEditor(modal);
        editorEditRefArticleAlreadyDone = true;
    }
};

function loadSpinnerAR(div) {
    div.removeClass('d-flex');
    div.addClass('d-none');
}

function loadAndDisplayInfos(select) {
    $('.newContent').removeClass('d-none');
    $('.newContent').addClass('d-block');

    $('span[role="textbox"]').each(function () {
        $(this).parent().css('border-color', '');
    });
}

$('#addFournisseur').click(function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            $('#addFournisseur').closest('div').before(dataReponse);
            ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
        }
    }
    if ($('.d-none.newContent').length === 0) {
        $('#addFournisseur').closest('select[name="fournisseur"]').css('border-color', '');
        let filled = true;
        $('input[name="referenceFournisseur"], input[name="labelFournisseur"]').each(function () {
            if ($(this).val() === '' && filled) {
                $(this).css('border-color', 'red');
                filled = false;
            } else {
                $(this).css('border-color', '');
            }
        });
        if (filled) {
            $('input[name="referenceFournisseur"], input[name="labelFournisseur"]').each(function () {
                $(this).css('border-color', '');
            });
            $('span[role="textbox"]').each(function () {
                $(this).parent().css('border-color', '');
            });
            let path = Routing.generate('ajax_render_add_fournisseur', true);
            xhttp.open("POST", path, true);
            xhttp.send();
        }
    } else {
        $('span[role="textbox"]').each(function () {
            if ($(this).text() === '') {
                $(this).parent().css('border-color', 'red');
            }
        });
    }
});

function deleteArticleFournisseur(button) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            $('#articleFournisseursEdit').html(dataReponse);
        }
    }

    let path = Routing.generate('ajax_render_remove_fournisseur', true);
    let sendArray = {};
    sendArray['articleF'] = $(button).data('value');
    sendArray['articleRef'] = $(button).data('title');
    let toSend = JSON.stringify(sendArray);
    xhttp.open("POST", path, true);
    xhttp.send(toSend);
}

function passArgsToModal(button) {
    $("#submitDeleteFournisseur").data('value', $(button).data('value'));
    $("#submitDeleteFournisseur").data('title', $(button).data('title'));
}

function addFournisseurEdit(button) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            $('#articleFournisseursEdit').after(dataReponse);
            ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
        }
    }
    let path = Routing.generate('ajax_render_add_fournisseur', true);
    xhttp.open("POST", path, true);
    xhttp.send();
};

