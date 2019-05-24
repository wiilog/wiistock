let ajaxDims = function () {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            $('#buttonModalDimSet').click();
        }
    }
    let data = $('#dimsForm').find('.data');
    let json = {};
    data.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        json[name] = val;
    })
    let Json = JSON.stringify(json);
    let path = Routing.generate('ajax_dimensions_etiquettes', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
    //TODO CG passer en jquery
}