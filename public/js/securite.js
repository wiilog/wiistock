function checkIfUserExists() {
    email = JSON.stringify($('#inputEmail').val());
    $.post(Routing.generate('check_email'), email, function (data) {
        if (data === 'inactiv') {
            $('#noUserActiv').click();
        } else if (data === true) {
            $('#noUser').click();
        } else {
            $('#isUser').click();
        }
    });

}