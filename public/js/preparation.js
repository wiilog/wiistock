$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

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
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
    ],
});

$('#submitSearchPrepaLivraison').on('click', function () {
    let statut = $('#statut').val();
    let type = $('#type').val();
    table
        .columns('Statut:name')
        .search(statut)
        .draw();

    table
        .columns('Type:name')
        .search(type)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = table.column('Date:name').index();
            let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

            if (
                (dateMin == "" && dateMax == "")
                ||
                (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

            ) {
                return true;
            }
            return false;
        }
    );
    table
        .draw();
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
let articlesChosen = {};
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
                $('#tableSplittingArticles').DataTable();
                $('#startSplitting').click();
            });
        }
    });
};

function submitSplitting(submit) {
    if ($('#scissionTitle').attr('data-restant') <= 0) {
        let path = Routing.generate('submit_splitting', true);
        let params = {
            'articles': articlesChosen,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
            'refArticle': submit.data('ref')
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

function limitInput(input) {
    let id = input.data('id');
    if (input.val() !== '') {
        input.val(Math.min(input.val(), input.data('quantite')));
    }
    articlesChosen[id] = input.val();
    console.log(articlesChosen);
}

function addToScissionAll(checkbox) {
    let toSee = 0;
    let input = $('#' + checkbox.data('id'));
    if (!checkbox.is(':checked')) {
        $('#scissionTitle').attr('data-restant', parseFloat($('#scissionTitle').attr('data-restant')) + parseFloat(checkbox.data('quantite')));
        input.prop('disabled', false);
        input.val('');
    } else {
        if ($('#scissionTitle').attr('data-restant') > 0) {
            input.val(checkbox.data('quantite'));
            input.prop('disabled', true);
            limitInput(input);
            $('#scissionTitle').attr('data-restant', ($('#scissionTitle').attr('data-restant') - checkbox.data('quantite')));
        } else {
            checkbox.prop('checked', false);
        }
    }
    if ($('#scissionTitle').attr('data-restant') > 0) {
        toSee = $('#scissionTitle').attr('data-restant');
    }
    $('#quantiteRestante').html('Quantité restante ' + toSee);
    $('#scissionTitle').html("Choix d'articles pour la référence " + checkbox.data('ref'));
}

function exitScissionModal() {
    articlesChosen = {};
}