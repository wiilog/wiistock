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