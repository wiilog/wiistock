const PAGE_DEM_COLLECTE = 'dcollecte';
const PAGE_DEM_LIVRAISON = 'dlivraison';
const PAGE_MANUT = 'manutention';
const PAGE_ORDRE_COLLECTE = 'ocollecte';
const PAGE_ORDRE_LIVRAISON = 'olivraison';
const PAGE_PREPA = 'prépa';
const PAGE_ARRIVAGE = 'arrivage';
const PAGE_IMPORT = 'import';
const PAGE_ALERTE = 'alerte';
const PAGE_RECEPTION = 'reception';
const PAGE_MVT_STOCK = 'mvt_stock';
const PAGE_MVT_TRACA = 'mvt_traca';
const PAGE_LITIGE_ARR = 'litige';
const PAGE_ENCOURS = 'encours';
const PAGE_INV_ENTRIES = 'inv_entries';
const PAGE_INV_MISSIONS = 'inv_missions';
const PAGE_INV_SHOW_MISSION = 'inv_mission_show';
const PAGE_RCPT_TRACA = 'reception_traca';
const PAGE_ACHEMINEMENTS = 'acheminement';
const PAGE_EMPLACEMENT = 'emplacement';
const PAGE_URGENCES = 'urgences';

const STATUT_ACTIF = 'disponible';
const STATUT_INACTIF = 'consommé';
const STATUT_EN_TRANSIT = 'en transit';

/** Constants which define a valid barcode */
const BARCODE_VALID_REGEX = /^[A-Za-z0-9_ \-]{1,21}$/;

// alert modals config
const AUTO_HIDE_DEFAULT_DELAY = 2000;

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

function submitAction(modal, path, table = null, callback = null, close = true, clear = true) {
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
    let datesToCheck = {};
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
        const $parent = $input.closest('[data-multiple-key]');

        if ($parent && $parent.length > 0) {
            const multipleKey = $parent.data('multiple-key');
            const objectIndex = $parent.data('multiple-object-index');
            Data[multipleKey] = (Data[multipleKey] || {});
            Data[multipleKey][objectIndex] = (Data[multipleKey][objectIndex] || {});
            Data[multipleKey][objectIndex][name] = val;
        } else {
            Data[name] = val;
        }

        const $formGroupLabel = $input.closest('.form-group').find('label');

        // Fix bug when we write <label>Label<select>...</select></label
        // the label variable had text options
        let label = $formGroupLabel
            .clone()    //clone the element
            .children() //select all the children
            .remove()   //remove all the children
            .end()      //again go back to selected element
            .text();


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
        // validation valeur des inputs datetimepicker - part 1/2
        if ($input.hasClass('date')) {
            if ($input.hasClass('first-date')) {
                datesToCheck.first = $input.val();
            } else if ($input.hasClass('last-date')) {
                datesToCheck.last = $input.val();
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
        if (!$(this).hasClass("no-data")) {
            Data[$(this).attr("name")] = $(this).is(':checked');
        }
    });
    $("div[name='id']").each(function () {
        Data[$(this).attr("name")] = $(this).attr('value');
    });
    modal.find(".elem").remove();

    // validation valeur des inputs datetimepicker - part 2/2
    let datesAreValid = (
        !datesToCheck.first ||
        !datesToCheck.last ||
        moment(datesToCheck.first, 'D/M/YYYY h:mm').isSameOrBefore(moment(datesToCheck.last, 'D/M/YYYY h:mm'))
    );

    // si tout va bien on envoie la requête ajax...
    if (!barcodeIsInvalid
        && missingInputs.length == 0
        && wrongNumberInputs.length == 0
        && passwordIsValid
        && datesAreValid) {
        if (close == true) {
            modal.find('.close').click();
        }
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
                table.ajax.reload(null, false);
            }

            if (clear) {
                clearModal(modal);
            }

            if (callback !== null) {
                callback(data);
            }
        }, 'json');

    } else {
        // ... sinon on construit les messages d'erreur
        let msg = '';

        // cas où il manque des champs obligatoires
        if (missingInputs.length > 0) {
            msg += 'Veuillez renseigner le champ ' + missingInputs.map((label) => (label && label.trim())).join(', ') + ". <br/>";
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
                            msg += ' doit être inférieure ou égal à ' + max + ".<br>";
                        } else if (typeof (max) == 'undefined') {
                            msg += ' doit être supérieure ou égal à ' + min + ".<br>";
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

        // cas où les dates ne sont pas dans le bon ordre
        if (!datesAreValid) {
            msg += "La date de début doit être antérieure à la date de fin.<br>";
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
        $('.list-multiple').select2();
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

function editRow(button, path, modal, submit, editorToInit = false, editor = '.editor-container-edit', setMaxQuantity = false, afterLoadingEditModal = () => {
}) {
    let id = button.data('id');
    let ref = button.data('ref');

    let json = {id: id, isADemand: 0};
    if (ref !== false) {
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
        $('.list-multiple').select2();
        toggleRequiredChampsLibres(modal.find('#typeEdit'), 'edit');

        if (setMaxQuantity) setMaxQuantityEdit($('#referenceEdit'));

        if (editorToInit) initEditor(editor);

        afterLoadingEditModal(modal);
    }, 'json');

}

function newModal(path, modal) {
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
}

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
}

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
}

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

function updateQuantityDisplay($elem) {
    let $modalBody = $elem.closest('.modal-body');
    let typeQuantite = $modalBody.find('.type_quantite').val();

    if (typeQuantite == 'reference') {
        $modalBody.find('.article').addClass('d-none');
        $modalBody.find('.reference').removeClass('d-none');

    } else if (typeQuantite == 'article') {
        $modalBody.find('.reference').addClass('d-none');
        $modalBody.find('.article').removeClass('d-none');
    }
}

function initFilterDateToday() {
    let $todayMinDate = $('#dateMin');
    let $todayMaxDate = $('#dateMax');
    if ($todayMinDate.val() === '' && $todayMaxDate.val() === '') {
        let today = moment().format('DD/MM/YYYY');
        $todayMinDate.val(today);
        $todayMaxDate.val(today);
    }
}

/**
 *
 * @param $select
 * @param {{}|{route: string, param: {}|undefined, success?: function(result, term)}} ajaxOptions
 * @param lengthMin
 * @param placeholder
 * @param {boolean} autoSelect
 */
function initSelect2($select, placeholder = '', lengthMin = 0, ajaxOptions = {}, {autoSelect, $nextField} = {}) {
    $select.each(function () {
        const $self = $(this);
        let isMultiple = $self.attr('multiple') === 'multiple';

        const select2AjaxOptions = ajaxOptions && ajaxOptions.route
            ? {
                ajax: {
                    url: Routing.generate(ajaxOptions.route, ajaxOptions.param || {}, true),
                    dataType: 'json',
                    delay: 250,
                    ...(
                        autoSelect
                            ? {
                                processResults: (data, {term}) => {
                                    const {results = []} = (data || {});

                                    if (results
                                        && results.length > 0
                                        && results[0].text === term) {

                                        const option = new Option(results[0].text, results[0].id, true, true);
                                        const oldVal = $self.val();
                                        const newVal = (
                                            !isMultiple
                                                ? option
                                                : [
                                                    ...(oldVal || []),
                                                    option
                                                ]
                                        );

                                        setTimeout(() => {
                                            $self
                                                .append(newVal)
                                                .trigger('change');

                                            $self.select2('close');
                                            if ($nextField) {

                                                if (!$nextField.data('select2')) {
                                                    $nextField.select2('open');
                                                }
                                                else {
                                                    $nextField.trigger('focus');
                                                }
                                            }
                                        });
                                    }
                                    return data;
                                }
                            }
                            : {}
                    )
                }
            }
            : {};

        const getSelect2Selection = () => (
            $self
                .siblings('.select2-container')
                .find('.select2-selection')
        );

        let $select2Selection = getSelect2Selection();

        if ($select2Selection.length > 0) {
            $select2Selection.off('focus');
        }

        $self.select2({
            ...select2AjaxOptions,
            language: {
                inputTooShort: function () {
                    let s = lengthMin > 1 ? 's' : '';
                    return 'Veuillez entrer au moins ' + lengthMin + ' caractère' + s + '.';
                },
                searching: function () {
                    return 'Recherche en cours...';
                },
                noResults: function () {
                    return 'Aucun résultat.';
                }
            },
            minimumInputLength: lengthMin,
            placeholder: {
                id: 0,
                text: placeholder,
            },
            allowClear: !isMultiple
        });

        // on recupère le select2 après l'initialisation de select2
        $select2Selection = getSelect2Selection();
        $select2Selection.on('focus', function() {
            if (!isMultiple) {
                $self.select2('open');
            }
        });
    });
}

function initDisplaySelect2(select, inputValue) {
    let data = $(inputValue).data();
    if (data.id && data.text) {
        let option = new Option(data.text, data.id, true, true);
        $(select).append(option).trigger('change');
    }
}

function initDisplaySelect2Multiple(select, inputValues) {
    let data = $(inputValues).data();
    if (data.id && data.text) {
        let idArr = data.id.toString().split(',');
        let textArr = data.text.split(',');

        for (let i = 0; i < idArr.length; i++) {
            let option = new Option(textArr[i], idArr[i], true, true);
            $(select).append(option).trigger('change');
        }
    }
}

function ajaxAutoCompleteEmplacementInit(select, autoSelectOptions, placeholder = '', lengthMin = 1) {
    initSelect2(select, placeholder, lengthMin, {route: 'get_emplacement'}, autoSelectOptions);
}

function ajaxAutoCompleteTransporteurInit(select) {
    initSelect2(select, '', 1, {route: 'get_transporteurs'});
}

function ajaxAutoRefArticleInit(select, typeQuantity = null) {
    initSelect2(select, '', 1, {
        route: 'get_ref_articles',
        param:
            {
                activeOnly: 1,
                typeQuantity
            }
    });
}

function ajaxAutoArticlesInit(select) {
    initSelect2(select, '', 1, {route: 'get_articles', param: {activeOnly: 1}});
}

function ajaxAutoArticlesReceptionInit(select, receptionId = null) {
    let reception = receptionId ? receptionId : $('#receptionId').val();
    initSelect2(select, '', 1, {route: 'get_article_reception', param: {reception: reception}});
}

function ajaxAutoFournisseurInit(select, placeholder = '') {
    initSelect2(select, placeholder, 1, {route: 'get_fournisseur'});
}

function ajaxAutoChauffeurInit(select) {
    initSelect2(select, '', 1, {route: 'get_chauffeur'});
}

function ajaxAutoUserInit(select, placeholder = '') {
    initSelect2(select, placeholder, 1, {route: 'get_user'});
}

function ajaxAutoDemandCollectInit(select) {
    initSelect2(select, 'Numéros de demande', 3, {route: 'get_demand_collect'});
}

function ajaxAutoDemandesInit(select) {
    initSelect2(select, 'Numéros de demande', 3, {route: 'get_demandes'});
}

let toggleRequiredChampsLibres = function (select, require) {
    let bloc = require == 'create' ? $('#typeContentNew') : $('#typeContentEdit'); //TODO pas top
    let params = {};
    if (select.val()) {
        bloc.find('.data').removeClass('needed');
        bloc.find('span.is-required-label').remove();
        params[require] = select.val();
        let path = Routing.generate('display_required_champs_libres', true);

        $.post(path, JSON.stringify(params), function (data) {
            if (data) {
                data.forEach(function (element) {
                    const $formControl = bloc.find('#' + element + require);
                    const $label = $formControl.siblings('label');
                    $label.append($('<span class="is-required-label">&nbsp;*</span>'));
                    $formControl.addClass('needed');
                });
            }
            $('.list-multiple').select2();
        }, 'json');
    }
}

function clearDiv() {
    $('.clear').html('');
}

function clearErrorMsg($div) {
    $div.closest('.modal').find('.error-msg').html('');
}

function clearInvalidInputs($modal) {
    let $inputs = $modal.find('.modal-body').find(".data");
    $inputs.each(function () {
        // on enlève les classes is-invalid
        $(this).removeClass('is-invalid');
        $(this).next().find('.select2-selection').removeClass('is-invalid');
    });
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
            if ($(this).hasClass('needs-default')) {
                $(this).val($(this).data('init'));
            } else {
                $(this).val("");
            }
        }
        // on enlève les classes is-invalid
        $(this).removeClass('is-invalid');
        $(this).next().find('.select2-selection').removeClass('is-invalid');
        //TODO protection ?
    });
    // on vide tous les select2
    let selects = $modal
        .find('.modal-body')
        .find('.ajax-autocomplete, .ajax-autocompleteEmplacement, .ajax-autocompleteFournisseur, .ajax-autocompleteTransporteur, .select2, .select2-free');
    selects.each(function () {
        if (!$(this).hasClass('no-clear')) {
            if ($(this).hasClass('needs-default')) {
                $(this).val($(this).data('init')).trigger('change');
            } else {
                $(this).val(null).trigger('change');
            }
        }
    });
    // let dataArrays = $modal
    //     .find('.modal-body')
    //     .find('.data-array');
    // dataArrays.each(function() {
    //     if ($(this).data('init') !== undefined) {
    //         $(this).val($(this).data('init'));
    //     }
    // });
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
        if (!$(this).hasClass('no-clear')) {
            if ($(this).hasClass('needs-default')) {
                $(this).prop('checked', $(this).data('init'));
            } else {
                $(this).prop('checked', false);
                $(this).removeClass('active');
                $(this).addClass('not-active');
            }
        }
    });
}

function alertErrorMsg(data, remove = false) {
    if (data !== true) {
        let $alertDanger = $('#alerts').find('.alert-danger');
        $alertDanger.removeClass('d-none');
        $alertDanger
            .css('display', 'block')
            .css('opacity', '1');

        if (remove) {
            $alertDanger.delay(2000).fadeOut(2000);
        }
        $alertDanger.find('.error-msg').html(data);
        $('html,body').animate({scrollTop: 0});
    }
}

function alertSuccessMsg(data, remove = true) {
    let $alertSuccess = $('#alerts').find('.alert-success');
    $alertSuccess.removeClass('d-none');
    $alertSuccess
        .css('display', 'block')
        .css('opacity', '1');

    if (remove) {
        $alertSuccess.delay(2000).fadeOut(2000);
    }
    $alertSuccess.find('.confirm-msg').html(data);
    $('html,body').animate({scrollTop: 0});
}

function saveFilters(page, tableSelector, callback) {
    let path = Routing.generate('filter_sup_new');

    const $filterDateMin = $('.filter-date-min');
    const $filterDateMax = $('.filter-date-max');
    const $filterDateMinPicker = $filterDateMin.data("DateTimePicker");
    const $filterDateMaxPicker = $filterDateMax.data("DateTimePicker");

    if ($filterDateMinPicker) {
        $filterDateMinPicker.format('YYYY-MM-DD');
    }
    if ($filterDateMaxPicker) {
        $filterDateMaxPicker.format('YYYY-MM-DD');
    }

    const valFunction = {
        'filter-input': ($input) => ($input.val() || '').trim(),
        'filter-select2': ($input) => ($input.select2('data') || [])
            .filter(({id, text}) => (id.trim() && text.trim()))
            .map(({id, text}) => ({id, text})),
        'filter-checkbox': ($input) => $input.is(':checked')
    };

    let params = {
        page,
        ...(Object.keys(valFunction).reduce((acc, key) => {
            const $fields = $('.filters-container').find(`.${key}`);
            const values = {};
            $fields.each(function () {
                const $elem = $(this);
                values[$elem.attr('name')] = valFunction[key]($elem);
            });

            return ({
                ...acc,
                ...values
            })
        }, {}))
    };

    if ($filterDateMinPicker) {
        $filterDateMinPicker.format('DD/MM/YYYY');
    }
    if ($filterDateMaxPicker) {
        $filterDateMaxPicker.format('DD/MM/YYYY');
    }
    $.post(path, JSON.stringify(params), function (response) {
        if (response) {
            if (callback) {
                callback();
            }
            if (tableSelector) {
                const $table = $(tableSelector);
                if ($table && $table.DataTable) {
                    $table.DataTable().draw();
                }
            }
        } else {
            alertErrorMsg('Veuillez saisir des filtres corrects (pas de virgule ni de deux-points).', true);
        }
    }, 'json');
}

function checkAndDeleteRow(icon, modalName, route, submit) {
    let $modalBody = $(modalName).find('.modal-body');
    let $submit = $(submit);
    let id = icon.data('id');

    let param = JSON.stringify(id);
    $submit.hide();
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
        $arrivageBlock.find('input').each(function () {
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
        $arrivageBlock.find('input').each(function () {
            $(this).addClass('needed');
        });
        $arrivageBlock.show();
        button.text('Sans Arrivage');
    }
    button.data('arrivage', !button.data('arrivage'));
};

let addArrivalAssociation = function (span) {
    let $arrivalInput = span.parent().find('.arrivalNb').first();
    let $parent = $arrivalInput.parent();
    $arrivalInput.clone().appendTo($parent);
};


function redirectToDemandeLivraison(demandeId) {
    window.open(Routing.generate('demande_show', {id: demandeId}));
}

/**
 * Manage on fly forms
 * Should instanciate onFlyFormOpened object in the script
 * @param id
 * @param button
 * @param forceHide
 */
function onFlyFormToggle(id, button, forceHide = false) {
    let $toShow = $('#' + id);
    let $toAdd = $('#' + button);
    if (!forceHide && $toShow.hasClass('invisible')) {
        $toShow.parent().parent().css("display", "flex");
        $toShow.parent().parent().css("height", "auto");
        $toShow.css("height", "auto");
        $toShow.removeClass('invisible');
        $toAdd.removeClass('invisible');
        onFlyFormOpened[id] = true;
    } else {
        $toShow
            .addClass('invisible')
            .css("height", "0");
        $toAdd.addClass('invisible');

        // we reset all field
        $toShow
            .find('.newFormulaire ')
            .each(function () {
                const $fieldNext = $(this).next();
                if ($fieldNext.is('.select2-container')) {
                    $fieldNext.removeClass('is-invalid');
                }

                $(this)
                    .removeClass('is-invalid')
                    .val('')
                    .trigger('change');
            });

        onFlyFormOpened[id] = false;

        const onFlyFormOpenedValues = Object.values(onFlyFormOpened);
        // si tous les formulaires sont cachés
        if (onFlyFormOpenedValues.length === 0 ||
            Object.values(onFlyFormOpened).every((opened) => !opened)) {
            $toShow.parent().parent().css("height", "0");
        }
    }
}


function onFlyFormSubmit(path, button, toHide, buttonAdd, $select = null) {
    let inputs = button.closest('.formulaire').find(".newFormulaire");
    let params = {};
    let formIsValid = true;
    inputs.each(function () {
        if ($(this).hasClass('neededNew') && ($(this).val() === '' || $(this).val() === null)) {
            $(this).addClass('is-invalid');
            const $fieldNext = $(this).next();
            if ($fieldNext.is('.select2-container')) {
                $fieldNext.addClass('is-invalid');
            }
            formIsValid = false;
        } else {
            $(this).removeClass('is-invalid');
            const $fieldNext = $(this).next();
            if ($fieldNext.is('.select2-container')) {
                $fieldNext.removeClass('is-invalid');
            }
        }
        params[$(this).attr('name')] = $(this).val();
    });
    if (formIsValid) {
        $.post(path, JSON.stringify(params), function (response) {
            if ($select) {
                let option = new Option(response.text, response.id, true, true);
                $select.append(option).trigger('change');
            }
            onFlyFormToggle(toHide, buttonAdd, true)
        });
    }
}

function initDateTimePicker(dateInput = '#dateMin, #dateMax', format = 'DD/MM/YYYY', minDate = false, defaultHours = null, defaultMinutes = null) {
    let options = {
        format: format,
        useCurrent: false,
        locale: moment.locale(),
        showTodayButton: true,
        showClear: true,
        icons: {
            clear: 'fas fa-trash',
        },
        tooltips: {
            today: 'Aujourd\'hui',
            clear: 'Supprimer',
            selectMonth: 'Choisir le mois',
            selectYear: 'Choisir l\'année',
            selectDecade: 'Choisir la décennie',
        }
    };
    if (minDate) {
        options.minDate = moment().hours(0).minutes(0).seconds(0);
    }
    if (defaultHours !== null && defaultMinutes !== null) {
        options.defaultDate = moment().hours(defaultHours).minutes(defaultMinutes);
    }
    $(dateInput).datetimepicker(options);
}

function toggleQuill($modal, enable) {
    $modal.find('.ql-editor').prop('contenteditable', enable);
}

function generateCSV(route, filename = 'export', param = null) {
    loadSpinner($('#spinner'));
    let data = param ? {'param': param} : {};

    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        moment(data['dateMin'], 'DD/MM/YYYY').format('YYYY-MM-DD');
        moment(data['dateMax'], 'DD/MM/YYYY').format('YYYY-MM-DD');
        let params = JSON.stringify(data);
        let path = Routing.generate(route, true);

        $.post(path, params, function (response) {
            if (response) {
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                dlFile(csv, filename);
                hideSpinner($('#spinner'));
            }
        }, 'json');
    } else {
        warningEmptyDatesForCsv();
        hideSpinner($('#spinner'));
    }
}

let dlFile = function (csv, filename) {
    // !!! remove a special char (first param is not empty) !!!
    // Fix temporaire en attendant d'exporter en server side !
    csv = csv.replace('﻿', '');
    csv = csv.replace("﻿", '');
    $.post(Routing.generate('get_encodage'), function (usesUTF8) {
        let encoding = usesUTF8 ? 'utf-8' : 'windows-1252';
        let d = new Date();
        let textEncode = new CustomTextEncoder(encoding, {NONSTANDARD_allowLegacyEncoding: true});
        let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
        date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
        let exportedFilenmae = filename + '-' + date + '.csv';
        let blob = new Blob([textEncode.encode(csv)], {type: 'text/csv;charset=' + encoding + ';'});
        saveAs(blob, exportedFilenmae);
    });
};

function warningEmptyDatesForCsv() {
    alertErrorMsg('Veuillez saisir des dates dans le filtre en haut de page.', true);
    $('#dateMin, #dateMax').addClass('is-invalid');
    $('.is-invalid').on('click', function () {
        $(this).parent().find('.is-invalid').removeClass('is-invalid');
    });
}

function displayFiltersSup(data) {
    data.forEach(function (element) {
        switch (element.field) {
            case 'utilisateurs':
            case 'providers':
            case 'reference':
            case 'statut':
            case 'carriers':
            case 'emplacement':
            case 'demCollecte':
            case 'demande':
                let valuesElement = element.value.split(',');
                let $select = $(`.filter-select2[name="${element.field}"]`);
                $select.empty();
                valuesElement.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let name = valueArray[1];
                    let option = new Option(name, id, true, true);
                    $select.append(option).trigger('change');
                });
                break;

            // multiple
            case 'natures':
                let valuesElement2 = element.value.split(',');
                let $select2 = $(`.filter-select2[name="${element.field}"]`);
                let ids = [];
                valuesElement2.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    ids.push(id);
                });
                $select2.val(ids).trigger('change');
                break;

            case 'emergency':
            case 'duty':
            case 'frozen':
                if (element.value === '1') {
                    $('#' + element.field + '-filter').attr('checked', 'checked');
                }
                break;

            case 'litigeOrigin':
                const text = element.value || '';
                const id = text.replace('é', 'e').substring(0, 3).toUpperCase();
                $(`.filter-checkbox[name="${element.field}"]`).val(id).trigger('change');
                break;

            case 'dateMin':
            case 'dateMax':
                const sourceFormat = (element.value && element.value.indexOf('/') > -1)
                    ? 'DD/MM/YYYY'
                    : 'YYYY-MM-DD';
                const $fieldDate = $(`.filter-input[name="${element.field}"]`);
                const dateValue = moment(element.value, sourceFormat).format('DD/MM/YYYY');
                if ($fieldDate.data("DateTimePicker")) {
                    $fieldDate.data("DateTimePicker").date(dateValue);
                } else {
                    $fieldDate.val(dateValue);
                }
                break;

            default:
                $('#' + element.field).val(element.value);
        }
    });
}

/**
 *
 * @param {string|undefined} title
 * @param $body jQuery object
 * @param {array} buttonConfig array of html config
 * @param {'success'|'warning'|'error'|undefined} iconType
 * @param {boolean} autoHide delay in milliseconds
 */
function displayAlertModal(title, $body, buttonConfig, iconType = undefined, autoHide = false) {
    const $alertModal = $('#alert-modal');
    hideSpinner($alertModal.find('.modal-footer .spinner'));
    $alertModal.find('.modal-footer-wrapper').removeClass('d-none');

    // set title
    const $modalHeader = $alertModal.find('.modal-header');
    const $modalTitle = $modalHeader.find('.modal-title');

    if (title) {
        $modalHeader.removeClass('d-none');
        $modalTitle.text(title);
    } else {
        $modalHeader.addClass('d-none');
        $modalTitle.empty();
    }

    const $modalBody = $alertModal.find('.modal-body');
    $modalBody
        .find('.bookmark-icon')
        .addClass('d-none')
        .removeClass('d-flex');

    // we display requested icon
    if (iconType) {
        $modalBody
            .find(`.bookmark-icon.bookmark-${iconType}`)
            .removeClass('d-none')
            .addClass('d-flex');
    }

    $modalBody
        .find('.modal-body-main')
        .html($body);

    // set buttons
    const $modalFooter = $alertModal.find('.modal-footer > .modal-footer-wrapper');
    if (buttonConfig && buttonConfig.length > 0) {
        $modalFooter.removeClass('d-none');
        const $wrapper = $('<div/>', {class: 'row justify-content-center'}).prepend(
            ...buttonConfig.map(({action, ...config}) => {
                return $('<div/>', {class: 'col-auto'}).append($('<button/>', {
                    ...config,
                    ...(action
                        ? {
                            click: () => {
                                action($alertModal)
                            }
                        }
                        : {})
                }));
            })
        );
        $modalFooter.html($wrapper);
    } else {
        $modalFooter.addClass('d-none');
        $modalFooter.empty();
    }

    if (autoHide) {
        setTimeout(() => {
            if ($alertModal.hasClass('show')) {
                $modalFooter.find('.btn-action-on-hide').trigger('click');
                $alertModal.modal('hide');
            }
        }, AUTO_HIDE_DEFAULT_DELAY)
    }

    $alertModal.modal('show');
}

function initTooltips($elements) {
    $elements.each(function () {
        $(this).tooltip('dispose');
        $(this).tooltip();
    });
}

function managePrintButtonTooltip(active, $button) {
    if ($button) {
        $button.tooltip(
            active ? undefined : 'dispose'
        )
    }
}

function initOnTheFlyCopies($elems) {
    $elems.each(function () {
        $(this).keyup(function () {
            $(this).closest('.form-group').find('.copiedOnTheFly').val($(this).val());
        })
    });
}

function initFreeSelect2($selects) {
    $selects.each(function () {
        const $self = $(this);
        $self.select2({
            tags: true,
            "language": {
                "noResults": function () {
                    return 'Ajoutez des éléments';
                }
            },
        });
        $self.next('.select2-container').find('.select2-selection').on('focus', () => {
            $(this).closest(".select2-container").siblings('select:enabled').select2('open');
        });
    });
}


function openSelect2($select2) {
    $select2.select2('open');
}

function saveExportFile(routeName, params = null) {
    const $spinner = $('#spinner');
    loadSpinner($spinner);

    const path = Routing.generate(routeName, true);

    const filtersData = {};
    $('.filterService input').each(function () {
        const $input = $(this);
        const name = $input.attr('name');
        const val = $input.val();
        if (name && val) {
            filtersData[name] = val;
        }
    });

    const data = {
        ...filtersData,
        ...(params || {})
    }

    if (data.dateMin && data.dateMax) {
        data.dateMin = moment(data.dateMin, 'DD/MM/YYYY').format('YYYY-MM-DD');
        data.dateMax = moment(data.dateMax, 'DD/MM/YYYY').format('YYYY-MM-DD');

        const dataKeys = Object.keys(data);

        const joinedData = dataKeys
            .map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`)
            .join('&');

        window.location.href = `${path}?${joinedData}`;
        hideSpinner($spinner);
    }
    else {
        warningEmptyDatesForCsv();
        hideSpinner($spinner);
    }
}
