$('.select2').select2();

//TYPE

const urlApiType = Routing.generate('type_api', true);
let tableType = $('#tableType_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": urlApiType,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'S\'applique' },
        { "data": 'Actions' },
    ],
});

let dataModalTypeNew = $("#modalNewType");
let ButtonSubmitTypeNew = $("#submitTypeNew");
let urlTypeNew = Routing.generate('type_new', true);
InitialiserModal(dataModalTypeNew, ButtonSubmitTypeNew, urlTypeNew, tableType, displayErrorType, false);

let dataModalTypeDelete = $("#modalDeleteType");
let ButtonSubmitTypeDelete = $("#submitDeleteType");
let urlTypeDelete = Routing.generate('type_delete', true);
InitialiserModal(dataModalTypeDelete, ButtonSubmitTypeDelete, urlTypeDelete, tableType, askForDeleteConfirmation, false);

let dataModalEditType = $("#modalEditType");
let ButtonSubmitEditType = $("#submitEditType");
let urlEditType = Routing.generate('type_edit', true);
InitialiserModal(dataModalEditType, ButtonSubmitEditType, urlEditType, tableType);


//CHAMPS LIBRE

const urlApiChampLibre = Routing.generate('champ_libre_api', { 'id': id }, true);
let tableChampLibre = $('#tableChamplibre_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": urlApiChampLibre,
        "type": "POST"
    },
    columns: [
        { "data": 'Label' },
        { "data": 'S\'applique' },
        { "data": 'Typage' },
        { "data": 'Valeur par défaut' },
        { "data": 'Elements' },
        { "data": 'Obligatoire à la création' },
        { "data": 'Obligatoire à la modification' },
        { "data": 'Actions' },
    ],
});

let dataModalChampLibreNew = $("#modalNewChampLibre");
let ButtonSubmitChampLibreNew = $("#submitChampLibreNew");
let urlChampLibreNew = Routing.generate('champ_libre_new', true);
InitialiserCLModal(dataModalChampLibreNew, ButtonSubmitChampLibreNew, urlChampLibreNew, tableChampLibre, displayErrorCL, false);

let dataModalChampLibreDelete = $("#modalDeleteChampLibre");
let submitChampLibreDelete = $("#submitChampLibreDelete");
let urlChampLibreDelete = Routing.generate('champ_libre_delete', true);
InitialiserCLModal(dataModalChampLibreDelete, submitChampLibreDelete, urlChampLibreDelete, tableChampLibre);

let dataModalEditChampLibre = $("#modalEditChampLibre");
let submitEditChampLibre = $("#submitEditChampLibre");
let urlEditChampLibre = Routing.generate('champ_libre_edit', true);
InitialiserCLModal(dataModalEditChampLibre, submitEditChampLibre, urlEditChampLibre, tableChampLibre);

function askForDeleteConfirmation(data) {
    let modal = $('#modalDeleteType');

    if (data !== true) {
        modal.find('.modal-body').html(data);
        let submit = $('#submitDeleteType');

        let typeId = submit.val();
        let params = JSON.stringify({ force: true, type: typeId });

        submit.on('click', function () {
            $.post(Routing.generate('type_delete'), params, function () {
                tableChampLibre.ajax.reload();
            }, 'json');
        });
    } else {
        modal.find('.close').click();
    }
}

let defaultValueForTypage = function (select, cible) {
    let valueDefault =  $('#valueDefault'+cible);
    valueDefault.find('.form-group').addClass('d-none');
    valueDefault.find('input').removeClass('data');

    let typage = select.val();
    let defaultBloc = $('#'+typage+cible);
    defaultBloc.removeClass('d-none');
    defaultBloc.find('input').addClass('data');
}

$(document).ready(function () {
    $('#typage').change(function () {
        if ($(this).val() === 'list') {
            $("#list").show();
            $("#noList").hide();
        } else {
            $("#list").hide();
            $("#noList").show();
            $("div").remove(".elem");
            $("#ajouterElem").remove();
        }
    });
});

function changeType(select) {
    if ($(select).val() === 'list') {
        $('#defaultValue').hide();
        $('#isList').show();
    } else {
        $('#isList').hide();
        $('#defaultValue').show();
    }
}

function displayErrorCL(data) {
    let modal = $("#modalNewChampLibre");
    let msg = 'Ce nom de champ libre existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
}

function displayErrorType(data) {
    let modal = $("#modalNewType");
    let msg = 'Ce nom de type existe déjà. Veuillez en choisir un autre.';
    displayError(modal, msg, data);
}

function InitialiserCLModal(modal, submit, path, table, callback = null, close = true) {
    submit.click(function () {
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {

            if (this.readyState == 4 && this.status == 200) {
                $('.errorMessage').html(JSON.parse(this.responseText));
                data = JSON.parse(this.responseText);

                if (data.redirect) {
                    window.location.href = data.redirect;
                }
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
            let $input = $(this);
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
            if ($(this).hasClass('data'))
                Data[$(this).attr("name")] = $(this).is(':checked');
        });
        $("div[name='id']").each(function () {
            Data[$(this).attr("name")] = $(this).attr('value');
        });
        modal.find(".elem").remove();
        // si tout va bien on envoie la requête ajax...
        if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
            if (close == true) modal.find('.close').click();
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

