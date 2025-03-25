import Form from "@app/form";
import {POST} from "@app/ajax";

$(function() {
    Form
        .create('.box-login')
        .submitTo(
            POST,
            `change_password_in_bdd`,
            {
                success: (response) => {
                    window.location.href = Routing.generate('login');
                },
                error: (response) => {
                    $(`.error-msg`).removeClass(`d-none`).html(response.msg);
                }
            }
        );
});
