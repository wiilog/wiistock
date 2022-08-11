function checkIfUserExists() {
    const email = JSON.stringify($('#inputEmail').val());
    $.post(Routing.generate('check_email'), email, function (data) {
        if (data === 'inactiv') {
            $('.error-msg').html('Votre compte est inactif, veuillez contacter l\'administrateur de votre application.');
        } else if (data === 'mailNotFound') {
            $('.error-msg').html('Il n\'existe pas de compte associé à cette adresse email.');
        } else {
            let $confirmMsg = $('#alert-success');
            $confirmMsg
                .find('.content')
                .html('Un lien pour réinitialiser le mot de passe de votre compte vient d\'être envoyé sur votre adresse email.');
            $confirmMsg
                .removeClass('d-none')
                .addClass('d-flex');
            $('.error-msg').html('');
        }
    });
}

function editPassword($button) {
    const result = Form.process($(`.password-container`));
    if (result) {
        wrapLoadingOnActionButton($button, () => (
            AJAX.route(`POST`, `change_password_in_bdd`, result.asObject())
                .json()
                .then(({success, msg}) => {
                    if (success) {
                        window.location.href = Routing.generate('login', {success: msg});
                    } else {
                        $(`.error-msg`).removeClass(`d-none`).html(msg);
                    }
                })
        ));
    }
}
