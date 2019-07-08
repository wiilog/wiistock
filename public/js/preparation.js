$('.select2').select2();

let path = Routing.generate('preparation_api');
let table = $('#table_id').DataTable({
    order: [[1, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 1
        }
    ],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: path,
    columns: [
        {"data": 'Numéro', 'title': 'Numéro'},
        {"data": 'Date', 'title': 'Date de création'},
        {"data": 'Statut', 'title': 'Statut'},
        {"data": 'Actions', 'title': 'Actions'},
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

let pathArticle = Routing.generate('preparation_article_api', {'id': id});
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: pathArticle,
    columns: [
        {"data": 'Référence CEA', 'title': 'Référence CEA'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
        {"data": 'Actions', 'title': 'Actions'},
    ],
});

let prepasToScission = [];
let articlesChosen = [];
let startPreparation = function (value) {
    let needScission;
    $.post(Routing.generate('need_scission', true), JSON.stringify({'demande': value.val()}), function (response) {
        needScission = response.need;
        if (!needScission) {
            xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    data = JSON.parse(this.responseText);
                    $('#startPreparation').addClass('d-none');
                    $('#finishPreparation').removeClass('d-none');
                    tableArticle.ajax.reload();
                    $('#statutPreparation').html(data);
                }
            };
            path = Routing.generate('preparation_take_articles', true);
            let params = {
                demande : value.val(),
                articles : articlesChosen
            };
            Json = JSON.stringify(params);
            xhttp.open("POST", path, true);
            xhttp.send(Json);
        } else {
            $.post(Routing.generate('start_scission', true), JSON.stringify({'demande': value.val()}), function (data) {
                prepasToScission = data.prepas;
                $('#scissionContent').html(prepasToScission[0]);
                $('#tableScissionArticles').DataTable();
                $('#startScission').click();
            });
        }
    });
};

function submitScission(submit) {
    if ($('#scissionTitle').data('restant') <= 0) {
        let path = Routing.generate('submit_scission', true);
        let params = {
            'articles': articlesChosen,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
        };
        $.post(path, JSON.stringify(params), function (response) {
            $('#modalScission').find('.close').click();
            if (submit.data('index') + 1 < prepasToScission.length) {
                articlesChosen = [];
                $('#scissionContent').html(prepasToScission[submit.data('index') + 1]);
                $('#tableScissionArticles').DataTable();
                $('#startScission').click();
            } else {
                xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function () {
                    if (this.readyState == 4 && this.status == 200) {
                        data = JSON.parse(this.responseText);
                        $('#startPreparation').addClass('d-none');
                        $('#finishPreparation').removeClass('d-none');
                        tableArticle.ajax.reload();
                        $('#statutPreparation').html(data);
                    }
                };
                path = Routing.generate('preparation_take_articles', true);
                let params = {
                    demande : submit.data('demande'),
                    articles : articlesChosen
                };
                Json = JSON.stringify(params);
                xhttp.open("POST", path, true);
                xhttp.send(Json);
            }
        })
    } else {
        $('.error-msg').html('Veuillez collecter l\'entièreté des articles');
    }
}

function addToScission(checkbox) {
    let toSee = 0;
    if (articlesChosen.includes(checkbox.data('id'))) {
        articlesChosen.splice(articlesChosen.indexOf(checkbox.data('id')), 1);
        $('#scissionTitle').attr('data-restant', parseFloat($('#scissionTitle').attr('data-restant')) + parseFloat(checkbox.data('quantite')));
    } else {
        articlesChosen.push(checkbox.data('id'));
        $('#scissionTitle').attr('data-restant', ($('#scissionTitle').attr('data-restant') - checkbox.data('quantite')));
    }
    if ($('#scissionTitle').attr('data-restant') > 0) {
        toSee = $('#scissionTitle').attr('data-restant');
    }
    $('#scissionTitle').html('Choix d\'articles pour la référence ' + checkbox.data('ref') + ' (Quantité restante à prélever : ' + toSee + ')');
}