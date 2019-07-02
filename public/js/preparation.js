$('.select2').select2();

let path = Routing.generate('preparation_api');
let table = $('#table_id').DataTable({
    order: [[1, 'desc']],
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: path,
    columns: [
        { "data": 'Numéro' },
        { "data": 'Date', 'title': 'Date de création' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateParts = a.split('/'),
            year = parseInt(dateParts[2]) - 1900,
            month = parseInt(dateParts[1]),
            day = parseInt(dateParts[0]);
        return Date.UTC(year, month, day, 0, 0, 0);
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

let pathArticle = Routing.generate('preparation_article_api', { 'id': id });
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: pathArticle,
    columns: [
        { "data": 'Référence CEA', 'title': 'Référence CEA' },
        { "data": 'Libellé', 'title': 'Libellé' },
        { "data": 'Emplacement', 'title': 'Emplacement' },
        { "data": 'Quantité', 'title': 'Quantité' },
        { "data": 'Quantité à prélever', 'title': 'Quantité à prélever' },
        { "data": 'Actions', 'title': 'Actions' },
    ],
});

let startPreparation = function (value) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $('#startPreparation').addClass('d-none');
            $('#finishPreparation').removeClass('d-none');
            tableArticle.ajax.reload();
            $('#statutPreparation').html(data);
        }
    }
    path = Routing.generate('preparation_take_articles', true);
    let demandeID = value.val();
    Json = JSON.stringify(demandeID);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}