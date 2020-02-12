$('.select2').select2();

let prepaHasBegun = false;

$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Statut');

    ajaxAutoDemandesInit($('.ajax-autocomplete-demande'));
    ajaxAutoCompleteEmplacementInit($('#preparation-emplacement'));
    $('#preparation-emplacement + .select2').addClass('col-6');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');

    let $filterDemand = $('.filters-container .filter-demand');
    $filterDemand.attr('name', 'demande');
    $filterDemand.attr('id', 'demande');
    let filterDemandId = $('#filterDemandId').val();
    let filterDemandValue = $('#filterDemandValue').val();

    if (filterDemandId && filterDemandValue) {
        let option = new Option(filterDemandValue, filterDemandId, true, true);
        $filterDemand.append(option).trigger('change');
    }
    else {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_PREPA);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});

let path = Routing.generate('preparation_api');
let table = $('#table_id').DataTable({
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[3, 'desc']],
    ajax: {
        url: path,
        'data' : {
            'filterDemand': $('#filterDemandId').val()
        },
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#table_id_filter input'), table);
    },
    columns: [
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: 0
        }
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

let pathArticle = Routing.generate('preparation_article_api', {'prepaId': $('#prepa-id').val()});
let tableArticle = $('#tableArticle_id').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: pathArticle,
    columns: [
        {"data": 'Actions', 'title': 'Actions'},
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
        {"data": 'Quantité prélevée', 'name': 'quantitePrelevee', 'title': 'Quantité prélevée'},
    ],
    order: [[1, "asc"]],
    columnDefs: [
        {'orderable': false, 'targets': [0]}
    ]

});

function startPicking($button) {
    let ligneArticleId = $button.attr('value');

    let path = Routing.generate('start_splitting', true);
    $.post(path, JSON.stringify(ligneArticleId), function (html) {
        $('#splittingContent').html(html);
        $('#tableSplittingArticles').DataTable({
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            dom: 'fltir',
            'lengthMenu': [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'tous']],
            'columnDefs' : [
                {'orderable': false, 'targets': [3]}
            ]
        });
        $('#startSplitting').click();
    });
};

let urlEditLigneArticle = Routing.generate('prepa_edit_ligne_article', true);
let modalEditLigneArticle = $("#modalEditLigneArticle");
let submitEditLigneArticle = $("#submitEditLigneArticle");
InitialiserModal(modalEditLigneArticle, submitEditLigneArticle, urlEditLigneArticle, tableArticle);

function submitSplitting(submit) {
    let $inputs = $('#tableSplittingArticles').find('.input');

    let articlesChosen = {};
    let quantityToZero = false;
    let maxExceeded = false;
    for(const input of $inputs) {
        const $input = $(input);
        const inputValue = $input.val() !== '' ? Number($input.val()) : '';
        const inputMax = $input.attr('max') !== '' ? Number($input.attr('max')) : 0;
        const inputValueInit = $input.data('value-init') !== '' ? Number($input.data('value-init')) : 0;

        if (inputValue !== '' && inputValue > 0) {
            if (inputValue <= inputMax) {
                let id = $input.data('id');
                articlesChosen[id] = inputValue;
                $input.removeClass('is-invalid');
            }
            else {
                maxExceeded = true;
                $input.addClass('is-invalid');
            }
        }
        else if (inputValueInit > 0) {
            quantityToZero = true;
            $input.addClass('is-invalid');
            break;
        }
    }

    if (maxExceeded) {
        $('#modalSplitting').find('.error-msg').html("Vous avez trop sélectionné pour un article.");
    }
    else if ($('#remainingQuantity').val() < 0) {
        $('#modalSplitting').find('.error-msg').html("Vous avez prélevé une quantité supérieure à celle demandée.");
    }
    else if (quantityToZero) {
        $('#modalSplitting').find('.error-msg').html("Vous ne pouvez pas renseigner de quantité inférieure à 1 pour cet article.");
    }
    else if (Object.keys(articlesChosen).length > 0) {
        let path = Routing.generate('submit_splitting', true);
        let params = {
            'articles': articlesChosen,
            'quantite': submit.data('qtt'),
            'demande': submit.data('demande'),
            'refArticle': submit.data('ref'),
            'preparation': submit.data('prep')
        };
        $.post(path, JSON.stringify(params), function (resp) {
            if (resp == true) {
                $('#modalSplitting').find('.close').click();
                tableArticle.ajax.reload();
            }
        });
    }
    else {
        $('#modalSplitting').find('.error-msg').html("Vous devez sélectionner une quantité pour enregistrer.");
    }
}

function updateRemainingQuantity() {
    let $inputs = $('#tableSplittingArticles').find('.input');

    let totalQuantityTaken = 0;
    $inputs.each(function() {
        if ($(this).val() != '') {
            totalQuantityTaken += parseFloat($(this).val()) - $(this).data('value-init');
        } else {
            totalQuantityTaken -= $(this).data('value-init');
        }
    });

    let quantityToTake = $('#scissionTitle').data('quantity-to-take');
    let remainingQuantity = quantityToTake - totalQuantityTaken;
    $('#quantiteRestante').html(String(Math.max(0, remainingQuantity)));
    $('#remainingQuantity').val(remainingQuantity);

    if (remainingQuantity < 0) {
        let s = remainingQuantity < -1 ? 's' : '';
        $('#modalSplitting').find('.error-msg').html('(' + -remainingQuantity + ' article' + s + ' en trop)');
        $('#quantiteRestante').parent().addClass('red');
        $('#quantiteRestante').parent().removeClass('green');
    } else if (remainingQuantity > 0) {
        $('#modalSplitting').find('.error-msg').html('');
        $('#quantiteRestante').parent().addClass('red');
        $('#quantiteRestante').parent().removeClass('green')
    } else {
        $('#modalSplitting').find('.error-msg').html('');
        $('#quantiteRestante').parent().removeClass('red');
        $('#quantiteRestante').parent().addClass('green');
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
    let value = Number($input.val());
    let thisMax = Number($input.attr('max'));

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

function clearEmplacementModal() {
    $('#preparation-emplacement').html('');
    $('#preparation-emplacement').val('');
    $('#select2-preparation-emplacement-container').html('');
}

function beginPrepa() {
    if (!prepaHasBegun) {
        let prepaId = $('#prepa-id').val();
        let path = Routing.generate('prepa_begin');

        $.post(path, prepaId, () => {
            prepaHasBegun = true;
        });
    }
}

function finishPrepa() {
    let allRowsEmpty = true;

    let rows = tableArticle
        .column('quantitePrelevee:name')
        .data();

    rows.each((elem) => {
        if (elem > 0) allRowsEmpty = false;
    });

    if (allRowsEmpty) {
        alertErrorMsg('Veuillez sélectionner au moins une ligne.', true);
    } else {
        clearEmplacementModal();
        $('#btnFinishPrepa').click();
    }
}
