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
    let password = $("#password").val();
    let password2 = $("#password2").val();
    let token = $("#token").val();
    let params = JSON.stringify({password: password, password2: password2, token:token});

    $.post(path, params, function(data){
        if (data === true) {
            $('.error-msg').html('Votre nouveau mot de passe a bien été enregistré.');
        } else if (data === false) {
            $('.error-msg').html('NON');
        } else {
            $('.error-msg').html('LOL');
        }
        },
        'json');
}
