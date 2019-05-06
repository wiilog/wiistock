$('.select2').select2();

var path = Routing.generate('preparation_api');
var table = $('#table_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[1, "desc"]],
    ajax: path,
    columns: [
        { "data": 'Numéro' },
        { "data": 'Date' },
        { "data": 'Statut' },
        { "data": 'Actions' },
    ],
});

var pathArticle = Routing.generate('preparation_article_api', { 'id': id });
var tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: pathArticle,
    columns: [
        { "data": 'Référence CEA', 'title': 'Référence CEA' },
        { "data": 'Libellé', 'title': 'Libellé' },
        { "data": 'Quantité', 'title': 'Quantité' },
        { "data": 'Quantité à prélever', 'title': 'Quantité à prélever' },
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
    path = Routing.generate('preparation_withdraw_articles', true);
    let demandeID = value.val();
    Json = JSON.stringify(demandeID);
    console.log(Json);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}

// let modalNewArticle = $("#modalNewArticle");
// let submitNewArticle = $("#submitNewArticle");
// let urlNewArticle = Routing.generate('preparation_add_article', true);
// InitialiserModal(modalNewArticle, submitNewArticle, urlNewArticle, tableArticle);

// let modalDeleteArticle = $("#modalDeleteArticle");
// let submitDeleteArticle = $("#submitDeleteArticle");
// let urlDeleteArticle = Routing.generate('preparation_delete_article', true);
// InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);
