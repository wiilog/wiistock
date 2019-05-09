$('.select2').select2();

let urlUtilisateur = Routing.generate('user_api', true);
let tableUser = $('#tableUser_id').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": urlUtilisateur,
        "type": "POST"
    },
    columns: [
        { "data": "Nom d'utilisateur" },
        { "data": "Email" },
        { "data": "Dernière connexion" },
        { "data": "Rôle" },
        { "data": 'Actions' },
    ],
});

let modalNewUser = $("#modalNewUser");
let submitNewUser = $("#submitNewUser");
let pathNewUser = Routing.generate('user_new', true);
InitialiserModal(modalNewUser, submitNewUser, pathNewUser, tableUser, alertErrorMsg);

let modalEditUser = $("#modalEditUser");
let submitEditUser = $("#submitEditUser");
let pathEditUser = Routing.generate('user_edit', true);
InitialiserModal(modalEditUser, submitEditUser, pathEditUser, tableUser, alertErrorMsg);

let modalDeleteUser = $("#modalDeleteUser");
let submitDeleteUser = $("#submitDeleteUser");
let pathDeleteUser = Routing.generate('user_delete', true);
InitialiserModalUser(modalDeleteUser, submitDeleteUser, pathDeleteUser, tableUser);

function alertErrorMsg(data) {
    if (data !== true) {
        alert(data); //TODO gérer erreur retour plus propre (alert bootstrap)
    }
}

function editRole(select) {
    let params = JSON.stringify({
        'role': select.val(),
        'userId': select.data('user-id')
    });

    $.post(Routing.generate('user_edit_role'), params, 'json');
}

function InitialiserModalUser(modal, submit, path, table, callback = null, close = true) {
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
            } else if (this.readyState == 4 && this.status == 250) {
                $('#cantDelete').click();
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