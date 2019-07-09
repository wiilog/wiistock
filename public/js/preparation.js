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

let prepasToSplit = [];
let articlesChosen = [];
let actualIndex = 0;
let startPreparation = function (value) {
    let path1 = Routing.generate('need_splitting', true);
    let params = {'demande': value.val()};

    $.post(path1, JSON.stringify(params), function (needSplitting) {
        if (!needSplitting) {
            let path2 = Routing.generate('preparation_take_articles', true);
            params.articles = articlesChosen;

            $.post(path2, JSON.stringify(params), function (data) {
                $('#startPreparation').addClass('d-none');
                $('#finishPreparation').removeClass('d-none');
                tableArticle.ajax.reload();
                $('#statutPreparation').html(data);
            })
        } else {
            let path3 = Routing.generate('start_splitting', true);
            $.post(path3, JSON.stringify(params), function (data) {
                prepasToSplit = data.prepas;
                $('#splittingContent').html(prepasToSplit[actualIndex]);
                $('#tableSplittingArticles').DataTable({
                    "language": {
                        url: "/js/i18n/dataTableLanguage.json",
                    },
                });
                $('#startSplitting').click();
            });
        }
    });
};

function submitSplitting(submit) {
    if ($('#scissionTitle').data('restant') <= 0) {
        let path = Routing.generate('submit_splitting', true);
        let params = {
            'articles': articlesChosen,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
        };
        $.post(path, JSON.stringify(params), function () {
            $('#modalSplitting').find('.close').click();
            if (actualIndex + 1 < prepasToSplit.length) {
                articlesChosen = [];
                actualIndex++;
                $('#splittingContent').html(prepasToSplit[actualIndex]);
                $('#tableSplittingArticles').DataTable();
                $('#startSplitting').click();
            } else {
                let path = Routing.generate('preparation_take_articles', true);
                $.post(path, JSON.stringify(params), function () {
                    $('#startPreparation').addClass('d-none');
                    $('#finishPreparation').removeClass('d-none');
                    tableArticle.ajax.reload();
                    $('#statutPreparation').html(data);
                });
            }
        })
    } else {
        $('.error-msg').html("Veuillez collecter le nombre total indiqué d'articles.");
    }
}

function addToScission(checkbox) {

    let toSee = 0;
    if (articlesChosen.includes(checkbox.data('id'))) {
        articlesChosen.splice(articlesChosen.indexOf(checkbox.data('id')), 1);
        $('#scissionTitle').attr('data-restant', parseFloat($('#scissionTitle').attr('data-restant')) + parseFloat(checkbox.data('quantite')));
    } else {
        if ($('#scissionTitle').attr('data-restant') > 0) {
            articlesChosen.push(checkbox.data('id'));
            $('#scissionTitle').attr('data-restant', ($('#scissionTitle').attr('data-restant') - checkbox.data('quantite')));
        } else {
            checkbox.prop('checked', false);
        }
    }
    if ($('#scissionTitle').attr('data-restant') > 0) {
        toSee = $('#scissionTitle').attr('data-restant');
    }
    $('#quantiteRestante').html('Quantité restante ' + toSee);
    $('#scissionTitle').html("Choix d'articles pour la référence " + checkbox.data('ref') + " (Quantité restante à prélever : " + toSee + ")");
}

function exitScissionModal() {
    articlesChosen = [];
}