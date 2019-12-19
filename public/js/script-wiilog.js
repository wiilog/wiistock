const PAGE_DEM_COLLECTE = 'dcollecte';
const PAGE_DEM_LIVRAISON = 'dlivraison';
const PAGE_MANUT = 'manutention';
const PAGE_ORDRE_COLLECTE = 'ocollecte';
const PAGE_ORDRE_LIVRAISON = 'olivraison';
const PAGE_PREPA = 'prépa';
const PAGE_ARRIVAGE = 'arrivage';
const PAGE_RECEPTION = 'reception';
const PAGE_MVT_STOCK = 'mvt_stock';
const PAGE_MVT_TRACA = 'mvt_traca';
const PAGE_LITIGE_ARR = 'litige_arrivage';
const PAGE_INV_ENTRIES = 'inv_entries';
const PAGE_RCPT_TRACA = 'reception_traca';
const PAGE_ACHEMINEMENTS = 'acheminement';

/** Constants which define a valid barcode */
const BARCODE_VALID_REGEX = /^[A-Za-z0-9_ \-]{1,21}$/;

$.fn.dataTable.ext.errMode = (resp) => {
    alert('La requête n\'est pas parvenue au serveur. Veuillez contacter le support si cela se reproduit.');
};

/**
 * Initialise une fenêtre modale
 *
 * pour utiliser la validation des données :
 *      ajouter une <div class="error-msg"> à la fin du modal-body
 *      ajouter la classe "needed" aux inputs qui sont obligatoires
 *      supprimerle data-dismiss=modal du bouton submit de la modale (la gestion de la fermeture doit se faire dans cette fonction)
 *      pour un affichage optimal de l'erreur, le label et l'input doivent être dans une div avec la classe "form-group"
 *
 * @param {Document} modal la fenêtre modale selectionnée : document.getElementById("modal").
 * @param {Document} submit le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {document} table le DataTable gérant les données
 *
 */
function InitialiserModal(modal, submit, path, table = null, callback = null, close = true, clear = true) {
    submit.click(function () {
        submitAction(modal, path, table, callback, close, clear);
    });
}

function submitAction(modal, path, table, callback, close, clear) {
    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let inputs = modal.find(".data");
    let inputsArray = modal.find(".data-array");
    let Data = {};
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    let barcodeIsInvalid = false;
    let name;
    let vals = [];
    inputsArray.each(function () {
        name = $(this).attr("name");
        vals.push($(this).val());
        Data[name] = vals;
    });

    inputs.each(function () {
        let $input = $(this);
        let val = $input.val();
        val = (val && typeof val.trim === 'function') ? val.trim() : val;
        name = $input.attr("name");
        Data[name] = val;
        let label = $input.closest('.form-group').find('label').text();
        // validation données obligatoires
        if ($input.hasClass('needed')
            && (val === undefined || val === '' || val === null || (Array.isArray(val) && val.length === 0))
            && $input.is(':disabled') === false) {
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);
            $input.addClass('is-invalid');
            $input.next().find('.select2-selection').addClass('is-invalid');

        } else {
            $input.removeClass('is-invalid');
        }

        if ($input.hasClass('is-barcode') && !isBarcodeValid($input)) {
            $input.addClass('is-invalid');
            $input.parent().addClass('is-invalid');
            label = label.replace(/\*/, '');
            barcodeIsInvalid = label;
        }

        // validation valeur des inputs de type number
        if ($input.attr('type') === 'number') {
            let val = parseInt($input.val());
            let min = parseInt($input.attr('min'));
            let max = parseInt($input.attr('max'));
            if (val > max || val < min) {
                wrongNumberInputs.push($input);
                $input.addClass('is-invalid');
            } else if (!isNaN(val)) {
                $input.removeClass('is-invalid');
            }
            if ($input.is(':disabled') === true) {
                $input.removeClass('is-invalid');
            }
        }
        // validation valeur des inputs de type password
        if ($input.attr('type') === 'password') {
            let password = $input.val();
            let isNotChanged = $input.hasClass('optional-password') && password === "";
            if (!isNotChanged) {
                if (password.length < 8) {
                    modal.find('.password-error-msg').html('Le mot de passe doit faire au moins 8 caractères.');
                    passwordIsValid = false;
                } else if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/)) {
                    modal.find('.password-error-msg').html('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial (@$!%*?&).');
                    passwordIsValid = false;
                } else {
                    passwordIsValid = true;
                }
            }
        }
    });

    // ... et dans les checkboxes
    let checkboxes = modal.find('.checkbox');
    checkboxes.each(function () {
        Data[$(this).attr("name")] = $(this).is(':checked');
    });
    $("div[name='id']").each(function () {
        Data[$(this).attr("name")] = $(this).attr('value');
    });
    modal.find(".elem").remove();

    // si tout va bien on envoie la requête ajax...
    if (!barcodeIsInvalid && missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
        if (close == true) modal.find('.close').click();

        $.post(path, JSON.stringify(Data), function (data) {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            // pour mise à jour des données d'en-tête après modification
            if (data.entete) {
                $('.zone-entete').html(data.entete)
            }
            if (table) {
                table.ajax.reload(function (json) {
                    if (data !== undefined) {
                        $('#myInput').val(json.lastInput);
                    }
                }, false);
            }

            if (clear) clearModal(modal);

            if (callback !== null) callback(data);
        }, 'json');

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
                // cas particulier alertes
                if (elem.prop('name') == 'limitSecurity' || elem.prop('name') == 'limitWarning') {
                    if (msg.indexOf('seuil de sécurité') == -1) {
                        msg += "Le seuil d'alerte doit être supérieur au seuil de sécurité.";
                    }
                } else {
                    let label = elem.closest('.form-group').find('label').text();
                    if (elem.is(':disabled') === false) {
                        msg += 'La valeur du champ ' + label.replace(/\*/, '');

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
                    }
                }
            });
        }

        // cas où le champ susceptible de devenir un code-barre ne respecte pas les normes
        if (barcodeIsInvalid) {
            msg += "Le champ " + barcodeIsInvalid + " doit contenir au maximum 21 caractères (lettres ou chiffres).<br>";
        }

        modal.find('.error-msg').html(msg);
    }
}

/**
 * Check if value in the given jQuery input is a valid barcode
 * @param $input
 * @return {boolean}
 */
function isBarcodeValid($input) {
    const value = $input.val();
    return Boolean(!value || BARCODE_VALID_REGEX.test(value));
}

//DELETE
function deleteRow(button, modal, submit) {
    let id = button.data('id');
    modal.find(submit).attr('value', id);
}

//SHOW
/**
 * Initialise une fenêtre modale
 *
 * @param {Document} modal la fenêtre modale selectionnée : document.getElementById("modal").
 * @param {Document} button le bouton qui va envoyé les données au controller via Ajax.
 * @param {string} path le chemin pris pour envoyer les données.
 *
 */
function showRow(button, path, modal) {
    let id = button.data('id');
    let params = JSON.stringify(id);

    $.post(path, params, function (data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}


//MODIFY
/**
 * La fonction modifie les valeurs d'une modale modifier avec les valeurs data-attibute.
 * Ces valeurs peuvent être trouvées dans datatableLigneArticleRow.html.twig
 *
 * @param {Document} button
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {Document} modal la modalde modification
 * @param {Document} submit le bouton de validation du form pour le edit
 *
 */

function editRow(button, path, modal, submit, editorToInit = false, editor = '.editor-container-edit', setMaxQuantity = false, afterLoadingEditModal = () => {}) {

    let id = button.data('id');
    let ref = button.data('ref');

    let json = {id: id, isADemand: 0};
    if (ref != false) {
        json.ref = ref;
    }

    modal.find(submit).attr('value', id);
    modal.find('#inputId').attr('value', id);

    $.post(path, JSON.stringify(json), function (resp) {
        modal.find('.modal-body').html(resp);
        modal.find('.select2').select2();
        ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur-edit'));
        ajaxAutoRefArticleInit($('.ajax-autocomplete-edit, .ajax-autocomplete-ref'));
        ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        ajaxAutoCompleteTransporteurInit(modal.find('.ajax-autocomplete-transporteur-edit'));
        ajaxAutoUserInit($('.ajax-autocomplete-user-edit'));

        if ($('#typageModif').val() !== undefined) {   //TODO Moche
            defaultValueForTypage($('#typageModif'), '-edit');
        }

        toggleRequiredChampsLibres(modal.find('#typeEdit'), 'edit');

        if (setMaxQuantity) setMaxQuantityEdit($('#referenceEdit'));

        if (editorToInit) initEditor(editor);

        afterLoadingEditModal()
    }, 'json');

}

function newModal(path, modal)
{
    $.post(path, function (resp) {
        modal.find('.modal-body').html(resp);
    }, 'json');
}

function setMaxQuantityEdit(select) {
    let params = {
        refArticleId: select.val(),
    };
    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        let modalBody = select.closest(".modal-body");
        modalBody.find('#quantite').attr('max', data);
    }, 'json');
}

function toggleRadioButton($button) {
    let sel = $button.data('title');
    let tog = $button.data('toggle');
    $('#' + tog).prop('value', sel);
    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function toggleLivraisonCollecte($button) {
    toggleRadioButton($button);

    let typeDemande = $button.data('title');
    let path = Routing.generate('demande', true);
    let demande = $('#demande');
    let params = JSON.stringify({demande: demande, typeDemande: typeDemande});
    let boutonNouvelleDemande = $button.closest('.modal').find('.boutonCreationDemande');

    $.post(path, params, function (data) {
        if (data === false) {
            $('.error-msg').html('Vous n\'avez créé aucune demande de ' + typeDemande + '.');
            boutonNouvelleDemande.removeClass('d-none');
            let pathIndex;
            if (typeDemande === 'livraison') {
                pathIndex = Routing.generate('demande_index', false);
            } else {
                pathIndex = Routing.generate('collecte_index', false);
            }

            boutonNouvelleDemande.find('#creationDemande').html(
                "<a href=\'" + pathIndex + "\'>Nouvelle demande de " + typeDemande + "</a>"
            );
            $button.closest('.modal').find('.plusDemandeContent').addClass('d-none');
        } else {
            ajaxPlusDemandeContent($button, typeDemande);
            $button.closest('.modal').find('.boutonCreationDemande').addClass('d-none');
            $button.closest('.modal').find('.plusDemandeContent').removeClass('d-none');
            $button.closest('.modal').find('.editChampLibre').removeClass('d-none');
        }
    }, 'json');
}

function initEditorInModal(modal) {
    initEditor(modal + ' .editor-container');
};

function initEditor(div) {
    // protection pour éviter erreur console si l'élément n'existe pas dans le DOM
    if ($(div).length) {
        return new Quill(div, {
            modules: {
                toolbar: [
                    [{header: [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline', 'image'],
                    [{'list': 'ordered'}, {'list': 'bullet'}]
                ]
            },
            formats: [
                'header',
                'bold', 'italic', 'underline', 'strike', 'blockquote',
                'list', 'bullet', 'indent',
                'link', 'image'
            ],
            theme: 'snow'
        });
    }
    return null;
};

//passe de l'éditeur à l'input pour envoi au back
function setCommentaire(div, quill = null) {
    // protection pour éviter erreur console si l'élément n'existe pas dans le DOM
    if ($(div).length && quill === null) {
        let container = div;
        let quill = new Quill(container);
        let com = quill.container.firstChild.innerHTML;
        $(div).closest('.modal').find('#commentaire').val(com);
    } else if (quill) {
        $(div).closest('.modal').find('#commentaire').val(quill.container.firstChild.innerHTML);
    }
};

//FONCTION REFARTICLE

//Cache/affiche les bloc des modal edit/new
function visibleBlockModal(bloc) {

    let blocContent = bloc.siblings().filter('.blocVisible');
    let sortUp = bloc.find('h3').find('.fa-sort-up');
    let sortDown = bloc.find('h3').find('.fa-sort-down');

    if (sortUp.attr('class').search('d-none') > 0) {
        sortUp.removeClass('d-none');
        sortUp.addClass('d-block');
        sortDown.removeClass('d-block');
        sortDown.addClass('d-none');

        blocContent.removeClass('d-none')
        blocContent.addClass('d-block');
    } else {
        sortUp.removeClass('d-block');
        sortUp.addClass('d-none');
        sortDown.removeClass('d-none');
        sortDown.addClass('d-block');

        blocContent.removeClass('d-block')
        blocContent.addClass('d-none')
    }
}

function typeChoice(bloc, text, content) {
    let cible = bloc.val();
    content.children().removeClass('d-block');
    content.children().addClass('d-none');

    $('#' + cible + text).removeClass('d-none');
    $('#' + cible + text).addClass('d-block');
}

function updateQuantityDisplay(elem) {
    let typeQuantite = $('#type_quantite').val();
    let modalBody = elem.closest('.modal-body');

    if (typeQuantite == 'reference') {
        modalBody.find('.article').addClass('d-none');
        modalBody.find('.reference').removeClass('d-none');

    } else if (typeQuantite == 'article') {
        modalBody.find('.reference').addClass('d-none');
        modalBody.find('.article').removeClass('d-none');
    }
}

function ajaxAutoCompleteEmplacementInit(select) {
    select.select2({
        ajax: {
            url: Routing.generate('get_emplacement'),
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
}

function ajaxAutoCompleteTransporteurInit(select) {
    select.select2({
        ajax: {
            url: Routing.generate('get_transporteurs'),
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
}

let ajaxAutoRefArticleInit = function (select, typeQuantity = null) {
    select.select2({
        ajax: {
            url: Routing.generate('get_ref_articles', {activeOnly: 1, typeQuantity}, true),
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
};

let ajaxAutoArticlesInit = function (select) {
    select.select2({
        ajax: {
            url: Routing.generate('get_articles', {activeOnly: 1}, true),
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
}

let ajaxAutoArticlesReceptionInit = function(select) {
    select.select2({
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
}

function ajaxAutoFournisseurInit(select, placeholder = '') {
    select.select2({
        ajax: {
            url: Routing.generate('get_fournisseur'),
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
        placeholder: {
            text: placeholder,
        }
    });
}

function ajaxAutoChauffeurInit(select) {
    select.select2({
        ajax: {
            url: Routing.generate('get_chauffeur'),
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
}

function ajaxAutoUserInit(select, placeholder = '') {
    select.select2({
        ajax: {
            url: Routing.generate('get_user'),
            dataType: 'json',
            delay: 250,
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 1 caractère.';
            },
            searching: function () {
                return 'Recherche en cours...';
            }
        },
        minimumInputLength: 1,
        placeholder: {
            text: placeholder,
        }
    });
}

function ajaxAutoDemandCollectInit(select) {
    select.select2({
        ajax: {
            url: Routing.generate('get_demand_collect'),
            dataType: 'json',
            delay: 250,
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 3 caractères.';
            },
            searching: function () {
                return 'Recherche en cours...';
            }
        },
        minimumInputLength: 3,
        placeholder: {
            text: 'Numéro demande'
        }
    });
}

let toggleRequiredChampsLibres = function (select, require) {
    let bloc = require == 'create' ? $('#typeContentNew') : $('#typeContentEdit'); //TODO pas top
    bloc.find('.data').removeClass('needed');

    let params = {};
    if (select.val()) {
        params[require] = select.val();
        let path = Routing.generate('display_required_champs_libres', true);

        $.post(path, JSON.stringify(params), function (data) {
            if (data) {
                data.forEach(function (element) {
                    bloc.find('#' + element + require).addClass('needed');
                });
            }
        }, 'json');
    }
}

function clearDiv() {
    $('.clear').html('');
}

function clearErrorMsg($div) {
    $div.closest('.modal').find('.error-msg').html('');
}

function displayError(modal, msg, success) {
    if (success === false) {
        modal.find('.error-msg').html(msg);
    } else {
        modal.find('.close').click();
    }
}

function clearModal(modal) {
    let $modal = $(modal);
    let inputs = $modal.find('.modal-body').find(".data");
    // on vide tous les inputs (sauf les disabled et les input hidden)
    inputs.each(function () {
        if ($(this).attr('disabled') !== 'disabled' && $(this).attr('type') !== 'hidden' && !$(this).hasClass('no-clear')) {
            $(this).val("");
        }
        if ($(this).attr('id') === 'statut') {
            $(this).val($(this).parent().find('span.active').data('title'));
        }
        // on enlève les classes is-invalid
        $(this).removeClass('is-invalid');
        $(this).next().find('.select2-selection').removeClass('is-invalid');
        //TODO protection ?
    });
    // on vide tous les select2
    let selects = $modal.find('.modal-body').find('.ajax-autocomplete,.ajax-autocompleteEmplacement, .ajax-autocompleteFournisseur, .ajax-autocompleteTransporteur, .select2');
    selects.each(function () {
        $(this).val(null).trigger('change');
    });
    // on vide les messages d'erreur
    $modal.find('.error-msg, .password-error-msg').html('');
    // on remet toutes les checkboxes sur off
    clearCheckboxes($modal);
    // on vide les éditeurs de texte
    $modal.find('.ql-editor').text('');
    // on vide les div identifiées comme à vider
    $modal.find('.clear').html('');
    $modal.find('.attachement').remove();
    $modal.find('.isRight').removeClass('isRight');
}

function clearCheckboxes($modal) {
    let checkboxes = $modal.find('.checkbox');
    checkboxes.each(function () {
        $(this).prop('checked', false);
        $(this).removeClass('active');
        $(this).addClass('not-active');
    });
}

function alertErrorMsg(data, remove = false) {
    if (data !== true) {
        let $alertDanger = $('#alerts').find('.alert-danger');
        $alertDanger.removeClass('d-none');
        $alertDanger
            .css('display', 'block')
            .css('opacity', '1');

        if (remove == true) {
            $alertDanger.delay(2000).fadeOut(2000);
        }
        $alertDanger.find('.error-msg').html(data);
    }
}

function alertSuccessMsg(data) {
    let $alertSuccess = $('#alerts').find('.alert-success');
    $alertSuccess.removeClass('d-none');
    $alertSuccess
        .css('display', 'block')
        .css('opacity', '1');
    $alertSuccess.delay(2000).fadeOut(2000);
    $alertSuccess.find('.confirm-msg').html(data);
}

function saveFilters(params, table = null) {
    let path = Routing.generate('filter_sup_new');

    $.post(path, JSON.stringify(params), function() {
        if (table) table.draw();
    }, 'json');
}

function checkAndDeleteRow(icon, modalName, route, submit) {
    let $modalBody = $(modalName).find('.modal-body');
    let $submit = $(submit);
    let id = icon.data('id');

    let param = JSON.stringify(id);

    $.post(Routing.generate(route), param, function (resp) {
        $modalBody.html(resp.html);
        if (resp.delete == false) {
            $submit.hide();
        } else {
            $submit.show();
            $submit.attr('value', id);
        }
    });
}

function toggleActiveButton($button, table) {
    $button.toggleClass('active');
    $button.toggleClass('not-active');

    let value = $button.hasClass('active') ? 'true' : '';
    table
        .columns('Active:name')
        .search(value)
        .draw();
}

function initSearchDate(table) {
    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = table.column('date:name').index();

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
}

/**
 * Create a jsPDF with right size
 * @param apiResponse
 * @returns {jsPDF}
 */
function createJsPDFBarcode(apiResponse) {
    const format = apiResponse.width > apiResponse.height ? 'l' : 'p';
    const docTemp = new jsPDF(format, 'mm', [apiResponse.height, apiResponse.width]);
    const pageSize = docTemp.internal.pageSize;

    const newWidth = apiResponse.width * (apiResponse.width / pageSize.getWidth());
    const newHeight = apiResponse.height * (apiResponse.height / pageSize.getHeight());
    return new jsPDF(format, 'mm', [newHeight, newWidth]);
}

/**
 * Save a pdf with all barcodes requested in the array requested.
 * @param {Array<string>} barcodes
 * @param apiResponse
 * @param {string} fileName
 */
function printBarcodes(barcodes, apiResponse, fileName, barcodesLabel = null) {
    // on vérifie la validité des code-barres
    barcodes.forEach(element => {
        if (!BARCODE_VALID_REGEX.test(element)) {
            alertErrorMsg('Le code-barre ' + element + ' ne peut pas être imprimé : il ne doit pas contenir de caractères spéciaux ni d\'accents.', true);
            return;
        }
    });

    if (!barcodes || barcodes.length === 0) {
        alertErrorMsg("Il n'y a rien à imprimer.", true);
    } else if (apiResponse.exists === false) {
        alertErrorMsg('Les dimensions étiquettes ne sont pas connues, veuillez les renseigner depuis le menu Paramétrage.', true);
    } else {
        const doc = createJsPDFBarcode(apiResponse);
        const docSize = doc.internal.pageSize;
        let docWidth = docSize.getWidth();
        let docHeight = docSize.getHeight();
        const docScale = (docWidth / docHeight);

        // to launch print for the document on the end of generation
        const imageLoaded = (new Array(barcodes.length)).fill(false);

        $("#barcodes").empty();
        barcodes.forEach(function (code, index) {
            const $img = $('<img/>', {id: "barcode" + index});
            $img.on('load', function () {
                const naturalScale = (this.naturalWidth / this.naturalHeight);
                const upperNaturalScale = (naturalScale >= docScale);
                let imageWidth = (upperNaturalScale
                    ? docWidth
                    : (docHeight * this.naturalWidth / this.naturalHeight));
                let imageHeight = (upperNaturalScale
                    ? (docWidth * this.naturalHeight / this.naturalWidth)
                    : docHeight);
                if (barcodesLabel) {
                    imageWidth *= 0.8;
                    imageHeight *= 0.8;
                }

                let posX = (upperNaturalScale
                    ? 0
                    : ((docWidth - imageWidth) / 2));
                let posY = (upperNaturalScale
                    ? ((docHeight - imageHeight) / 2)
                    : 0);

                if (barcodesLabel) {
                    posX = (docWidth - imageWidth) / 2;
                    posY = 0;
                    let maxSize = getFontSizeByText(barcodesLabel[index], docWidth, docHeight, imageHeight, doc);
                    doc.setFontSize(Math.min(maxSize, (docHeight - imageHeight)/1.5));
                    doc.text(barcodesLabel[index], docWidth / 2, imageHeight, {align: 'center', baseline: 'top'});
                }
                doc.addImage($(this).attr('src'), 'JPEG', posX, posY, imageWidth, imageHeight);
                doc.addPage();

                imageLoaded[index] = true;

                if (imageLoaded.every(loaded => loaded)) {
                    doc.deletePage(doc.internal.getNumberOfPages());
                    doc.save(fileName);
                }
            });
            $('#barcodes').append($img);
            JsBarcode("#barcode" + index, code, {
                format: "CODE128",
            });
        });
    }
}

function getFontSizeByText(text, docWidth, docHeight, imageHeight, doc) {
    let texts = text.split("\n");

    let maxLength = texts[0].length;
    texts.map(v => maxLength = Math.max(maxLength, v.length));
    let longestText = texts.filter(v => v.length == maxLength);

    let textWidth = doc.getTextWidth(longestText[0]);
    let size = (docWidth * .95 / textWidth) * doc.getFontSize();

    return size;
}

function hideSpinner(div) {
    div.removeClass('d-flex');
    div.addClass('d-none');
}

function loadSpinner(div) {
    div.removeClass('d-none');
    div.addClass('d-flex');

}

function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}

function displayRight(div) {
    div.addClass('isRight');
    div.removeClass('isWrong');
}

function displayWrong(div) {
    div.removeClass('isRight');
    div.addClass('isWrong');
}

function displayNeutral(div) {
    div.removeClass('isRight');
    div.removeClass('isWrong');
}

let submitNewAssociation = function () {
    let correct = true;
    let params = {};
    $('#modalNewAssociation').find('.needed').each(function (index, input) {
        if ($(input).val() !== '') {
            if (params[$(input).attr('name')]) {
                params[$(input).attr('name')] += ';' + $(input).val();
            } else {
                params[$(input).attr('name')] = $(input).val();
            }
        } else if (!$(input).hasClass('arrival-input')) {
            correct = false;
        }
    });
    if (correct) {
        let routeNewAssociation = Routing.generate('reception_traca_new', true);
        $.post(routeNewAssociation, JSON.stringify(params), function () {
            $('#modalNewAssociation').find('.close').click();
            if (typeof tableRecep !== "undefined") tableRecep.ajax.reload();
            $('#modalNewAssociation').find('.error-msg').text('');
        })
    } else {
        $('#modalNewAssociation').find('.error-msg').text('Veuillez renseigner tous les champs nécessaires.');
    }
};

let toggleArrivage = function (button) {
    let $arrivageBlock = $('.arrivalNb').first().parent();
    if (button.data('arrivage')) {
        $arrivageBlock.find('input').each(function() {
            if ($(this).hasClass('arrivage-input')) {
                $(this).remove();
            } else {
                $(this).val('');
                $(this).removeClass('needed');
            }
        });
        $arrivageBlock.hide();
        button.text('Avec Arrivage');
    } else {
        $arrivageBlock.find('input').each(function() {
            $(this).addClass('needed');
        });
        $arrivageBlock.show();
        button.text('Sans Arrivage');
    }
    button.data('arrivage', !button.data('arrivage'));
};

let addArrivalAssociation = function(span) {
    let $arrivalInput = span.parent().find('.arrivalNb').first();
    let $parent = $arrivalInput.parent();
    $arrivalInput.clone().appendTo($parent);
};

function overrideSearch($input, table) {
    $input.off();
    $input.on('keyup', function(e) {
        if (e.key === 'Enter'){
            table.search(this.value).draw();
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function addToRapidSearch(checkbox) {
    let alreadySearched = [];
    $('#rapidSearch tbody td').each(function() {
        alreadySearched.push($(this).html());
    });
    if (!alreadySearched.includes(checkbox.data('name'))) {
        let tr = '<tr><td>' + checkbox.data('name') + '</td></tr>';
        $('#rapidSearch tbody').append(tr);
    } else {
        $('#rapidSearch tbody tr').each(function() {
            if ($(this).find('td').html() === checkbox.data('name')) {
                if ($('#rapidSearch tbody tr').length > 1) {
                    $(this).remove();
                } else {
                    checkbox.prop( "checked", true );
                }
            }
        });
    }
}

function newLine(path, button, toHide, buttonAdd)
{
    let inputs = button.closest('.formulaire').find(".newFormulaire");
    let params = {};
    let formIsValid = true;

    inputs.each(function () {
        if ($(this).hasClass('neededNew') && ($(this).val() === '' || $(this).val() === null))
        {
            $(this).addClass('is-invalid');
            formIsValid = false;
        } else {
            $(this).removeClass('is-invalid');
        }
        params[$(this).attr('name')] = $(this).val();
    });

    if (formIsValid) {
        $.post(path, JSON.stringify(params), function () {
            let $toShow = $('#' + toHide);
            let $toAdd = $('#' + buttonAdd);
            $toShow.css('visibility', "hidden");
            $toAdd.css('visibility', "hidden");
            numberOfDataOpened--;
            if (numberOfDataOpened === 0) {
                $toShow.parent().parent().css("display", "none");
            }
        });
    }
}
