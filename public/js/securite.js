function checkIfUserExists() {
    email = JSON.stringify($('#inputEmail').val());
    $.post(Routing.generate('check_email'), email, function (data) {
        if (data === 'inactiv') {
            $('.error-msg').html('Votre compte est inactif, veuillez contacter l\'administrateur de votre application.');
        } else if (data === 'mailNotFound') {
            $('.error-msg').html('Il n\'existe pas de compte associé à cette adresse mail.');
        } else {
            let $confirmMsg = $('.confirm-msg');
            $confirmMsg.html('Un lien pour réinitialiser le mot de passe de votre compte vient d\'être envoyé sur votre adresse email.');
            $confirmMsg.closest('.alert').removeClass('d-none');

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
        if (data === 'ok') {
            window.location.href = Routing.generate('login', { 'info': 'Votre mot de passe a bien été modifié.' });
        } else {
            $('.error-msg').html(data);
        }
    }, 'json');
}
