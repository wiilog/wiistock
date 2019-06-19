function checkIfUserExists() {
    email = JSON.stringify($('#inputEmail').val());
    $.post(Routing.generate('check_email'), email, function (data) {
        if (data === 'inactiv') {
            $('.error-msg').html('Votre compte est inactif, veuillez contacter l\'administrateur de votre application.');
        } else if (data === true) {
            $('.error-msg').html('Il n\'existe pas de compte associé à cette adresse mail.');
        } else {
            $('.error-msg').html('Votre nouveau mot de passe vous a été envoyé par mail.');
        }
    });
}
function editPassword() {
    let path = Routing.generate('change_password_in_bdd', true);
    let password = $("password");
    let password2 = $("password2");
    let params = JSON.stringify({password: password, password2: password2});

    $.post(path, params,
        function(password, password2){console.log('good : '+ password + password2)},
        'json');
    console.log('lol');
}
