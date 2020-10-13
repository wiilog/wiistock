$(document)

function submitTransferRequest(e, route) {
    e.preventDefault();
    let that = this;
    let $form = $(this);

    $.ajax({
        url : route,
        type: $form.attr('method'),
        data : $form.serialize(),
        success: function(html) {
            if(html.success) {
                if(html.redirect) {
                    window.location.href = html.redirect
                } else {
                    $form.parent(".modal").modal('hide');
                }
            } else {
                $form.replaceWith($(html).find('form[name="transfer_request"]'));

                $('form[name="transfer_request"]')
                    .off("submit")
                    .submit(e => submitTransferRequest.call(that, e, route));
            }
        }
    });

}
