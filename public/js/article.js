let pathArticle = Routing.generate('article_api', true);
let tableArticle;
$(function () {
    initTableArticle();
});

function initTableArticle() {
    $.post(Routing.generate('article_api_columns'), function (columns) {
        tableArticle = $('#tableArticle_id')
            .on('error.dt', function (e, settings, techNote, message) {
                console.log('An error has been reported by DataTables: ', message);
            }).DataTable({
                serverSide: true,
                processing: true,
                paging: true,
                scrollX: true,
                order: [[1, 'asc']],
                "language": {
                    url: "/js/i18n/dataTableLanguage.json",
                },
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
                initComplete: function () {
                    loadSpinner($('#spinner'));
                    init();
                    overrideSearchArticle();
                    hideAndShowColumns(columns);
                },
                columns: columns.map((column) => ({
                    ...column,
                    class: undefined
                })),
                "drawCallback": function (settings) {
                    resizeTable();
                },
            });
    });
}

let resetNewArticle = function (element) {
    element.removeClass('d-block');
    element.addClass('d-none');
};

function hideAndShowColumns(columns) {
    tableArticle.columns().every(function(index) {
        this.visible(columns[index].class !== 'hide');
    });
}

function init() {
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
    let modalEditArticle = $("#modalEditArticle");
    let submitEditArticle = $("#submitEditArticle");
    let urlEditArticle = Routing.generate('article_edit', true);
    InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

    let modalNewArticle = $("#modalNewArticle");
    let submitNewArticle = $("#submitNewArticle");
    let urlNewArticle = Routing.generate('article_new', true);
    InitialiserModal(modalNewArticle, submitNewArticle, urlNewArticle, tableArticle);

    let modalDeleteArticle = $("#modalDeleteArticle");
    let submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('article_delete', true);
    InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

    let modalColumnVisible = $('#modalColumnVisible');
    let submitColumnVisible = $('#submitColumnVisible');
    let urlColumnVisible = Routing.generate('save_column_visible_for_article', true);
    InitialiserModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);

    tableArticle.on('responsive-resize', function (e, datatable) {
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
};

function loadAndDisplayInfos(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_reference_by_fournisseur', true);
        let fournisseur = $(select).val();
        let params = JSON.stringify(fournisseur);

        $.post(path, params, function (data) {
            $('#newContent').html(data);
            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
            initNewArticleEditor("#modalNewArticle");
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
        })
    }
}

let getArticleFournisseur = function () {
    xhttp = new XMLHttpRequest();
    let $articleFourn = $('#newContent');
    let modalfooter = $('#modalNewArticle').find('.modal-footer');
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);

            if (data.content) {
                modalfooter.removeClass('d-none')
                $articleFourn.parent('div').addClass('d-block');
                $articleFourn.html(data.content);
                $('.error-msg').html('')
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
                initNewArticleEditor("#modalNewArticle");
            } else if (data.error) {
                $('.error-msg').html(data.error)
            }
        }
    }
    path = Routing.generate('ajax_article_new_content', true)
    let data = {};
    $('#newContent').html('');
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
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                data = JSON.parse(this.responseText);
                if (data === false) {
                    $('.error-msg').html('Vous ne pouvez par créer d\'article quand la quantité est gérée à la référence.');
                } else {
                    fournisseur.removeClass('d-none');
                    fournisseur.find('select').html(data);
                    $('.error-msg').html('');
                }
            }
        };
        path = Routing.generate('ajax_fournisseur_by_refarticle', true)
        $('#newContent').html('');
        fournisseur.addClass('d-none');
        modalfooter.addClass('d-none')
        let refArticleId = select.val();
        let json = {};
        json['refArticle'] = refArticleId;
        Json = JSON.stringify(json);
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    }
};

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    $('#' + tog).prop('value', sel);

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function overrideSearchArticle() {
    let $input = $('#tableArticle_id_filter input');
    $input.off();
    $input.on('keyup', function(e) {
        let $printBtn = $('.justify-content-end').find('.printButton');
        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $printBtn.addClass('btn-disabled');
                $printBtn.removeClass('btn-primary');
            } else {
                $printBtn.removeClass('btn-disabled');
                $printBtn.addClass('btn-primary');
            }
            tableArticle.search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $printBtn.addClass('btn-disabled');
            $printBtn.removeClass('btn-primary');
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function printArticlesBarCodes() {
    let listArticles = $("#listArticleIdToPrint").val();
    const length = tableArticle.page.info().length;

    if (length > 0) {
        let path = Routing.generate(
            'article_print_bar_codes',
            {
                length,
                listArticles: listArticles,
                start: tableArticle.page.info().start
            },
            true
        );
        window.open(path, '_blank');
    }
    else {
        alertErrorMsg("Il n'y a aucun article à imprimer");
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
    $.post(Routing.generate('update_user_searches_for_article', true), json, function (data) {
        $("#modalRapidSearch").find('.close').click();
        tableArticle.search(tableArticle.search()).draw();
    });
}

function showOrHideColumn(check) {

    let columnName = check.data('name');

    let column = tableArticle.column(columnName + ':name');

    column.visible(!column.visible());

    let tableRefArticleColumn = $('#tableArticle_id_wrapper');
    tableRefArticleColumn.find('th, td').removeClass('hide');
    tableRefArticleColumn.find('th, td').addClass('display');
    check.toggleClass('data');
}

function displayActifOrInactif(select){
    let activeOnly = select.is(':checked');
    let path = Routing.generate('article_actif_inactif');

    $.post(path, JSON.stringify({activeOnly: activeOnly}), function(){
        tableArticle.ajax.reload();
    });
}
