import Form from "@app/form";
import {POST} from "@app/ajax";

$(function() {
    Form
        .create('.form-signin')
        .submitTo(
            POST,
            `reset_password_request`
        )
})
