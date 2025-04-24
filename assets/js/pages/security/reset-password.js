import Form from "@app/form";
import {POST} from "@app/ajax";
import Routing from '@app/fos-routing';

$(function() {
    Form
        .create('.box-login')
        .submitTo(
            POST,
            `change_password_in_bdd`,
            {
                success: (response) => {
                    window.location.href = Routing.generate('login', {messageCode: response.messageCode});
                },
                error: (response) => {
                    $(`.error-msg`).removeClass(`d-none`).html(response.msg);
                }
            }
        );
});
