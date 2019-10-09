$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

let $submitSearchPrepa = $('#submitSearchPrepaLivraison');

$(function() {
    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_PREPA);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0)$submitSearchPrepa.click();
    }, 'json');
});

let path = Routing.generate('preparation_api');
let table = $('#table_id').DataTable({
    order: [[1, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 2
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

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Date:name').index();

        if (typeof indexDate === "undefined") return true;

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

$submitSearchPrepa.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let utilisateur = $('#utilisateur').val()
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    let type = $('#type').val();

    saveFilters(PAGE_PREPA, dateMin, dateMax, statut, utilisateurPiped, type);

    table
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    table
        .columns('Type:name')
        .search(type ? '^' + type + '$' : '', true, false)
        .draw();

    table
        .columns('Opérateur:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    table.draw();
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
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
        {"data": 'Actions', 'title': 'Actions'},
    ],
});

let prepasToSplit = [];
let actualIndex = 0;
let startPreparation = function (value) {
    let path1 = Routing.generate('need_splitting', true);
    let params = {'demande': value.val()};

    $.post(path1, JSON.stringify(params), function (needSplitting) {
        if (!needSplitting) {
            let path2 = Routing.generate('preparation_take_articles', true);

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
    let $inputs = $('#tableSplittingArticles').find('.input');

    let articlesChosen = {};
    $inputs.each(function() {
        if ($(this).val() !== '' && $(this).val() > 0) {
            let id = $(this).data('id');
            articlesChosen[id] = $(this).val();
        }
    });

    if (parseFloat($('#quantiteRestante').html()) === 0) {
        let path = Routing.generate('submit_splitting', true);
        let params = {
            'articles': articlesChosen,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
            'refArticle': submit.data('ref')
        };
        $.post(path, JSON.stringify(params), function (resp) {
            if (resp == true) {
                $('#modalSplitting').find('.close').click();
                if (actualIndex + 1 < prepasToSplit.length) {
                    articlesChosen = [];
                    actualIndex++;
                    $('#splittingContent').html(prepasToSplit[actualIndex]);
                    $('#tableSplittingArticles').DataTable({
                        "language": {
                            url: "/js/i18n/dataTableLanguage.json",
                        },
                    });
                    $('#startSplitting').click();
                } else {
                    let path = Routing.generate('preparation_take_articles', true);
                    $.post(path, JSON.stringify(params), function (data) {
                        $('#startPreparation').addClass('d-none');
                        $('#finishPreparation').removeClass('d-none');
                        tableArticle.ajax.reload();
                        $('#statutPreparation').html(data);
                    });
                }
            } else {
                $('#modalSplitting').find('.error-msg').html("Les quantités ne correspondent pas.")
            }
        })
    } else {
        $('.error-msg').html("Veuillez collecter le nombre total indiqué d'articles.");
    }
}

function updateRemainingQuantity() {
    let $inputs = $('#tableSplittingArticles').find('.input');

    let totalQuantityTaken = 0;
    $inputs.each(function() {
        if ($(this).val() != '') {
            totalQuantityTaken += parseFloat($(this).val());
        }
    });

    let quantityToTake = $('#scissionTitle').data('quantity-to-take');
    let remainingQuantity = quantityToTake - totalQuantityTaken;
    $('#quantiteRestante').html(String(Math.max(0, remainingQuantity)));

    if (remainingQuantity < 0) {
        let s = remainingQuantity < -1 ? 's' : '';
        $('#modalSplitting').find('.error-msg').html('(' + -remainingQuantity + ' article' + s + ' en trop)');
        $('#quantiteRestante').parent().addClass('red');
    } else {
        $('#modalSplitting').find('.error-msg').html('');
        $('#quantiteRestante').parent().removeClass('red');
    }
}

function addToScissionAll($checkbox) {
    let $input = $checkbox.closest('td').find('.input');

    if (!$checkbox.is(':checked')) {
        $input.prop('disabled', false);
        $input.val('');
        limitInput($input);
    } else {
        if (parseFloat($('#quantiteRestante').html()) > 0) {
            $input.val($checkbox.data('quantite'));
            limitInput($input);
            $input.prop('disabled', true);
        } else {
            $checkbox.prop('checked', false);
        }
    }

    updateRemainingQuantity();
}

function limitInput($input) {
    // if (input.val().includes('.')) {
    //     input.val(Math.trunc(input.val()));
    // }
    // if (input.val().includes('-')) {
    //     input.val(input.val().replace('-', ''));
    // }
    // if (input.val().includes(',')) {
    //
    // }
    //

    // vérification quantité disponible référence
    let value = $input.val();
    let thisMax = $input.attr('max');
    if (value > thisMax) {
        $input.parent().find('.row-error-msg').html('max : ' + thisMax);
    } else {
        $input.parent().find('.row-error-msg').html('');
    }

    // let max = Math.min(
    //     parseFloat($('#quantiteRestante').html()),
    //     $input.data('quantite')
    // );
    //
    // if ($input.val() !== '' && $input.val() > max) {
    //     console.log($input.val() + ' > ' + max);
    //     $input.parent().find('.error-msg').html('valeur max : ' + max);
    // //     input.val(Math.min(input.val(), (max >= 0 ? max : 0)));
    // }

}