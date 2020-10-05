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
            order: [[1, 'asc']],
            ajax: {
                "url": pathArticle,
                "type": "POST",
                'dataSrc': function (json) {
                    $('#listArticleIdToPrint').val(json.listId);
                    if (!$(".statutVisible").val()) {
                        tableArticle.column('Statut:name').visible(false);
                    }
                    return json.data;
                }
            },
            columns: columns.map(function (column) {
                return {
                    ...column,
                    class: column.title === 'Actions' ? 'noVis' : undefined,
                    title: column.title === 'Actions' ? '' : column.title
                }
            }),
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

let resetNewArticle = function (element) {
    element.removeClass('d-block');
    element.addClass('d-none');
};

function init() {
    Select2.supplier($('.ajax-autocompleteFournisseur'));
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

    let modalColumnVisible = $('#modalColumnVisible');
    let submitColumnVisible = $('#submitColumnVisible');
    let urlColumnVisible = Routing.generate('save_column_visible_for_article', true);
    InitModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);

    tableArticle.on('responsive-resize', function () {
        resizeTable();
    });
}
function resizeTable() {
    tableArticle
        .columns.adjust()
        .responsive.recalc();
}

function initNewArticleEditor(modal) {
    initEditor(modal + ' .editor-container-new');
    $('.list-multiple').select2();
}

function loadAndDisplayInfos(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_reference_by_fournisseur', true);
        let fournisseur = $(select).val();
        let params = JSON.stringify(fournisseur);

        $.post(path, params, function (data) {
            $('#newContent').html(data);
            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
            initNewArticleEditor("#modalNewArticle");
            Select2.location($('.ajax-autocomplete-location'));
        })
    }
}

let getArticleFournisseur = function () {
    let xhttp = new XMLHttpRequest();
    let $articleFourn = $('#newContent');
    let modalfooter = $('#modalNewArticle').find('.modal-footer');
    xhttp.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200) {
            data = JSON.parse(this.responseText);

            if (data.content) {
                modalfooter.removeClass('d-none')
                $articleFourn.parent('div').addClass('d-block');
                $articleFourn.html(data.content);
                registerNumberInputProtection($articleFourn.find('input[type="number"]'));
                $('.error-msg').html('')
                Select2.location($('.ajax-autocomplete-location'));
                initNewArticleEditor("#modalNewArticle");
            } else if (data.error) {
                $('.error-msg').html(data.error)
            }
        }
    }
    let path = Routing.generate('ajax_article_new_content', true)
    let data = {};
    $articleFourn.html('');
    data['referenceArticle'] = $('#referenceCEA').val();
    data['fournisseur'] = $('#fournisseurID').val();
    $articleFourn.html('')
    modalfooter.addClass('d-none')
    if (data['referenceArticle'] && data['fournisseur']) {
        json = JSON.stringify(data);
        xhttp.open("POST", path, true);
        xhttp.send(json);
    }
};

function clearNewArticleContent(button) {
    button.parent().addClass('d-none');
    let $modal = button.closest('.modal');
    $modal.find('#fournisseur').addClass('d-none');
    $modal.find('#referenceCEA').val(null).trigger('change');
    $('#newContent').html('');
    $('#reference').html('');
    clearModal('#' + $modal.attr('id'));
}

let ajaxGetFournisseurByRefArticle = function (select) {
    if (select.val()) {
        let fournisseur = $('#fournisseur');
        let modalfooter = $('#modalNewArticle').find('.modal-footer');
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState === 4 && this.status === 200) {
                let data = JSON.parse(this.responseText);
                if (data === false) {
                    $('.error-msg').html('Vous ne pouvez par créer d\'article quand la quantité est gérée à la référence.');
                } else {
                    fournisseur.removeClass('d-none');
                    fournisseur.find('select').html(data);
                    $('.error-msg').html('');
                }
            }
        };
        let path = Routing.generate('ajax_fournisseur_by_refarticle', true)
        $('#newContent').html('');
        fournisseur.addClass('d-none');
        modalfooter.addClass('d-none')
        let refArticleId = select.val();
        let json = {};
        json['refArticle'] = refArticleId;
        let Json = JSON.stringify(json);
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    }
};

function printArticlesBarCodes($button, event) {
    if (!$button.hasClass('dropdown-item') || !$button.hasClass('disabled')) {
        let listArticles = $("#listArticleIdToPrint").val();
        const length = tableArticle.page.info().length;

        if (length > 0) {
            window.location.href = Routing.generate(
                'article_print_bar_codes',
                {
                    length,
                    listArticles: listArticles,
                    start: tableArticle.page.info().start
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

function saveRapidSearch() {
    let searchesWanted = [];
    $('#rapidSearch tbody td').each(function () {
        searchesWanted.push($(this).html());
    });
    let params = {
        recherches: searchesWanted
    };
    let json = JSON.stringify(params);
    $.post(Routing.generate('update_user_searches_for_article', true), json, function () {
        $("#modalRapidSearch").find('.close').click();
        tableArticle.search(tableArticle.search()).draw();
    });
}

function displayActifOrInactif(select){
    let activeOnly = select.is(':checked');
    let path = Routing.generate('article_actif_inactif');

    $.post(path, JSON.stringify({activeOnly: activeOnly}), function(){
        tableArticle.ajax.reload();
    });
}
