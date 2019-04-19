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


function InitialiserModal(modal, submit, path, table, callback = null, close = true) {

    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {

            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText));
                data = JSON.parse(this.responseText);

                if (data.redirect) {
                    window.location.href = data.redirect;
                }
                console.log(data)
                // pour mise à jour des données d'en-tête après modification
                if (data.entete) {
                    $('.zone-entete').html(data.entete)
                }
                table.ajax.reload(function (json) {

                    if (this.responseText !== undefined) {
                        $('#myInput').val(json.lastInput);

                    }
                });

                let inputs = modal.find('.modal-body').find(".data");
                // on vide tous les inputs (sauf les disabled)
                inputs.each(function () {
                    // $(this).val("");
                    if ($(this).attr('disabled') !== 'disabled') {
                        $(this).val("");
                    }
                    // on enlève les classes is-invalid
                    $(this).removeClass('is-invalid');
                });
                // on vide tous les select2
                let selects = modal.find('.modal-body').find('.ajax-autocomplete,.select2');
                selects.each(function () {
                    $(this).val(null).trigger('change');
                });
                // on vide les messages d'erreur
                modal.find('.error-msg, .password-error-msg').html('');
                // on remet toutes les checkboxes sur off
                let checkboxes = modal.find('.checkbox');
                checkboxes.each(function () {
                    $(this).prop('checked', false);
                });

                if (callback !== null) callback(data);

            }
        };

        // On récupère toutes les données qui nous intéressent
        // dans les inputs...
        let inputs = modal.find(".data");
        let Data = {};
        let missingInputs = [];
        let wrongNumberInputs = [];
        let passwordIsValid = true;
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
                    wrongNumberInputs.push($(this));
                    $(this).addClass('is-invalid');
                }
            }
            // validation valeur des inputs de type password
            if ($(this).attr('type') === 'password') {
                let password = $(this).val();
                let isNotChanged = $(this).hasClass('optional-password') && password === "";
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
        if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
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
    });
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
function showRow(modal, button, path) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            if (dataReponse) {
                modal.find('.modal-body').html(dataReponse);
            } else {
                //TODO gérer erreur
            }
        }
    }
    let json = button.data('id');
    xhttp.open("POST", path, true);
    xhttp.send(json);
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

function editRow(button, path, modal, submit, editorToInit = false) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            modal.find('.modal-body').html(dataReponse);
            ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur-edit'));
            ajaxAutoRefArticleInit($('.ajax-autocomplete-edit'));
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
            ajaxAutoUserInit($('.ajax-autocomplete-user-edit'));
            displayRequireChamp($('#typeEdit'), 'edit')
            if (editorToInit) initEditor('#' + modal.attr('id'));
        }
    }
    let json = button.data('id');
    modal.find(submit).attr('value', json);
    modal.find('#inputId').attr('value', json);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}

function toggleRadioButton(button) {
    let sel = button.data('title');
    let tog = button.data('toggle');
    $('#' + tog).prop('value', sel);


    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}


//initialisation editeur de texte une seule fois

function initEditor(modal) {

    var quill = new Quill(modal + ' .editor-container', {
        modules: {
            //     toolbar: [
            //         [{ header: [1, 2, 3, false] }],
            //         ['bold', 'italic', 'underline'],
            //         [{'list': 'ordered'}, {'list': 'bullet'}]
            //         ['image', 'code-block']
            //     ]
            // },
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'image'],

                [{ 'list': 'ordered' }, { 'list': 'bullet' }]
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
};

//passe de l'éditeur à l'imput pour insertion en BDD par la class editor-container
function setCommentaire(button) {
    let modal = button.closest('.modal');
    let container = '#' + modal.attr('id') + ' .editor-container';
    var quill = new Quill(container);
    // let commentaire = modal.find('input[id=commentaire]');
    com = quill.container.firstChild.innerHTML;
    $('#commentaire').val(com);

};

//passe de l'éditeur à l'imput pour insertion en BDD par l'id commentaireID (cas de conflit avec la class)
function setCommentaireID(button) {
    let modal = button.closest('.modal');
    let container = '#' + modal.attr('id') + ' .editor-container';
    var quill = new Quill(container);
    // let commentaire = modal.find('input[id=commentaireID]');
    com = quill.container.firstChild.innerHTML;
    $('#commentaireID').val(com);
};


//FONCTION REFARTICLE

//Cache/affiche les bloc des modal edit/new
function visibleBlockModal(bloc) {
    let blocContent = bloc.siblings().filter('.col-12');
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
    let typeQuantite = elem.closest('.radio-btn').find('#type_quantite').val();
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

let ajaxAutoRefArticleInit = function (select) {

    select.select2({
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

function clearNewContent(button) {
    button.parent().addClass('d-none');
    $('#newContent').html('');
    $('#reference').html('');
}

let displayRequireChamp = function (select, require) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            data.forEach(function (element) {
                $('#'+element+require).addClass('needed');
            });
        }
    }
    let path = Routing.generate('display_require_champ', true);
    let json = {};
    json[require] = select.val();
    let Json = JSON.stringify(json)
    $('.data').removeClass('needed');
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}
