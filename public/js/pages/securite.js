function checkIfUserExists($button) {
    const result = Form.process($(`.form-signin`));
    if(result) {
        wrapLoadingOnActionButton($button, () => (
            AJAX.route(`POST`, `check_email`, result.asObject())
                .json()
        ));
    }
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
