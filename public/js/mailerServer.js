let ajaxMailerServer = function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            $('#buttonModalMailerSet').click();
        }
    }
    let data = $('#mailerServerForm').find('.data');
    let json = {};
    data.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        json[name] = val;
    })
    let Json = JSON.stringify(json);
    let path = Routing.generate('ajax_mailer_server', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}