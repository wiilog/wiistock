let pathArticle = Routing.generate('article_api', true);
let tableArticle;
let $printTag ;
$(function () {
    $printTag = $('#printTag');
    initTableArticle();
    managePrintButtonTooltip(true, $printTag.is('button') ? $printTag.parent() : $printTag);
});

function initTableArticle() {
    $.post(Routing.generate('article_api_columns'), function (columns) {
        let tableArticleConfig = {
            serverSide: true,
            processing: true,
            paging: true,
            order: [[1, 'desc']],
            ajax: {
                url: pathArticle,
                type: 'POST',
                dataSrc: function (json) {
                    if (!$(".statutVisible").val()) {
                        tableArticle.column('Statut:name').visible(false);
                    }

                    return json.data;
                }
            },
            columns,
            drawConfig: {
                needsResize: true
            },
            rowConfig: {
                needsRowClickAction: true
            },
            hideColumnConfig: {
                columns,
                tableFilter: 'tableArticle_id_filter'
            }
        };
        tableArticle = initDataTable('tableArticle_id', tableArticleConfig);
        init();
    });
}

function resetNewArticle(element) {
    element.removeClass('d-block');
    element.addClass('d-none');
}

function init() {
    Select2Old.provider($('.ajax-autocomplete-fournisseur'));
    let $modalEditArticle = $("#modalEditArticle");
    let $submitEditArticle = $("#submitEditArticle");
    let urlEditArticle = Routing.generate('article_edit', true);
    InitModal(
        $modalEditArticle,
        $submitEditArticle,
        urlEditArticle,
        {
            tables: [tableArticle],
            keepModal: true,
            success: (data) => {
                if (data && data.success) {
                    $modalEditArticle.modal('hide');
                }
            }
        });

    let $modalNewArticle = $("#modalNewArticle");
    let $submitNewArticle = $("#submitNewArticle");
    let urlNewArticle = Routing.generate('article_new', true);
    InitModal($modalNewArticle, $submitNewArticle, urlNewArticle, { tables: [tableArticle] });

    let $modalDeleteArticle = $("#modalDeleteArticle");
    let $submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('article_delete', true);
    InitModal($modalDeleteArticle, $submitDeleteArticle, urlDeleteArticle, { tables: [tableArticle] });
}

function loadAndDisplayInfos(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_reference_by_fournisseur', true);
        let fournisseur = $(select).val();
        let params = JSON.stringify(fournisseur);

        $.post(path, params, function (data) {
            $('#newContent').html(data);
            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
            console.log('gfdgd');
            Select2Old.location($('.ajax-autocomplete-location'));
        })
    }
}

function getArticleFournisseur() {
    let $articleFourn = $('#newContent');
    let modalfooter = $('#modalNewArticle').find('.modal-footer');

    let data = {};
    $articleFourn.html('');
    data['referenceArticle'] = $('#referenceCEA').val();
    data['fournisseur'] = $('#fournisseurID').val();
    $articleFourn.html('')
    modalfooter.addClass('d-none')

    let path = Routing.generate('ajax_article_new_content', true)
    let json = JSON.stringify(data);
    if (data['referenceArticle'] && data['fournisseur']) {
        $.post(path, json).then((data) => {
            if (data.content) {
                modalfooter.removeClass('d-none')
                $articleFourn.parent('div').addClass('d-block');
                $articleFourn.html(data.content);
                Wiistock.registerNumberInputProtection($articleFourn.find('input[type="number"]'));
                $('.error-msg').html('')
                Select2Old.location($('.ajax-autocomplete-location'));
            } else if (data.error) {
                $('.error-msg').html(data.error)
            }
        });
    }
}

function clearNewArticleContent(button) {
    button.parent().addClass('d-none');
    let $modal = button.closest('.modal');
    $modal.find('#fournisseur').addClass('d-none');
    $modal.find('#referenceCEA').val(null).trigger('change');
    $('#newContent').html('');
    $('#reference').html('');
    clearModal('#' + $modal.attr('id'));
}

function ajaxGetFournisseurByRefArticle(select) {
    let refArticleId = select.val();
    let json = {};
    json['refArticle'] = refArticleId;

    if (select.val()) {
        let fournisseur = $('#fournisseur');
        let modalfooter = $('#modalNewArticle').find('.modal-footer');

        let path = Routing.generate('ajax_fournisseur_by_refarticle', true)
        let params = JSON.stringify(json);
        $.post(path, params).then((data) => {
            if (!data) {
                $('.error-msg').html('Vous ne pouvez par créer d\'article quand la quantité est gérée à la référence.');
            } else {
                fournisseur.removeClass('d-none');
                fournisseur.find('select').html(data);
                $('.error-msg').html('');
            }
        });

        $('#newContent').html('');
        fournisseur.addClass('d-none');
        modalfooter.addClass('d-none')
    }
}

function printArticlesBarCodes($button, event) {
    if (!$button.hasClass('dropdown-item') || !$button.hasClass('disabled')) {
        let listArticles = $('.article-row-id')
            .map((_, element) => $(element).val())
            .toArray();

        if (tableArticle.page.info().length > 0) {
            window.location.href = Routing.generate(
                'article_print_bar_codes',
                {
                    listArticles: listArticles,
                },
                true
            );
        } else {
            showBSAlert("Il n'y a aucun article à imprimer", 'danger');
        }
    }
    else if (event) {
        event.stopPropagation();
    }
}

function displayActifOrInactif(select){
    let activeOnly = select.is(':checked');
    let path = Routing.generate('article_actif_inactif');

    $.post(path, JSON.stringify({activeOnly: activeOnly}), function(){
        tableArticle.ajax.reload();
    });
}
