const PAGE_DEM_COLLECTE = 'dcollecte';
const PAGE_DEM_LIVRAISON = 'dlivraison';
const PAGE_MANUT = 'manutention';
const PAGE_ORDRE_COLLECTE = 'ocollecte';
const PAGE_ORDRE_LIVRAISON = 'olivraison';
const PAGE_PREPA = 'prépa';
const PAGE_ARRIVAGE = 'arrivage';
const PAGE_MVT_STOCK = 'mvt_stock';
const PAGE_MVT_TRACA = 'mvt_traca';
const PAGE_LITIGE_ARR = 'litige_arrivage';

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
    let Data = {};
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    let barcodeIsInvalid = false;

    inputs.each(function () {
        let $input = $(this);
        let val = $input.val();
        let name = $input.attr("name");
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

        // if ($input.hasClass('is-barcode') && !isBarcodeValid($input)) {
        //     $input.addClass('is-invalid');
        //     $input.parent().addClass('is-invalid');
        //     barcodeIsInvalid = label;
        // }


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
            msg += "Le champ " + barcodeIsInvalid + " ne doit pas contenir d'accent et être composé de maximum 21 caractères.<br>";
        }

        modal.find('.error-msg').html(msg);
    }
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

            console.log(pathIndex);
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
    let cible = bloc.val()
    content.children().removeClass('d-block');
    content.children().addClass('d-none');

    $('#' + cible + text).removeClass('d-none')
    $('#' + cible + text).addClass('d-block')
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
            url: Routing.generate('get_Transporteur'),
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

function ajaxAutoFournisseurInit(select) {
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
    });
}

function ajaxAutoUserInit(select) {
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
        if ($(this).attr('disabled') !== 'disabled' && $(this).attr('type') !== 'hidden') {
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
    $modal.find('.clear').html('')
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
        if (remove == true) $alertDanger.delay(2000).fadeOut(2000);
        $alertDanger.find('.error-msg').html(data);
    }
}

function alertSuccessMsg(data) {
    let $alertSuccess = $('#alerts').find('.alert-success');
    $alertSuccess.removeClass('d-none');
    $alertSuccess.delay(2000).fadeOut(2000);
    $alertSuccess.find('.confirm-msg').html(data);
}

function saveFilters(page, dateMin, dateMax, statut, user, type = null, location = null, colis = null, carriers = null, providers = null) {
    let path = Routing.generate('filter_sup_new');
    let params = {};
    if (dateMin) params.dateMin = dateMin;
    if (dateMax) params.dateMax = dateMax;
    if (statut) params.statut = statut;
    if (user) params.user = user;
    if (type) params.type = type;
    if (location) params.location = location;
    if (colis) params.colis = colis;
    if (carriers) params.carriers = carriers;
    if (providers) params.providers = providers;
    params.page = page;

    $.post(path, JSON.stringify(params), 'json');
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
    if (barcodes && barcodes.length) {
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
    } else {
        alertErrorMsg('Les dimensions étiquettes ne sont pas connues, veuillez les renseigner dans le menu Paramétrage.');
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