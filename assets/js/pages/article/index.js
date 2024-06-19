import '@styles/details-page.scss';
import Routing from '@app/fos-routing';
import {togglePrintButton} from "@app/utils";
import AJAX from "@app/ajax";

let tableArticle;
let $printTag ;

global.displayActifOrInactif = displayActifOrInactif;
global.printArticlesBarCodes = printArticlesBarCodes;

$(function () {
    $printTag = $('#printTag');
    initTableArticle();
});

function initTableArticle() {
    const referenceFilter = $(`[name=referenceFilter]`)
        .val()
        .trim();

    $.post(Routing.generate('article_api_columns'), function (columns) {
        let tableArticleConfig = {
            serverSide: true,
            processing: true,
            paging: true,
            order: [[1, 'desc']],
            ajax: {
                url: Routing.generate('article_api', true),
                type: AJAX.POST,
                dataSrc: function (json) {
                    if (!$(".statutVisible").val()) {
                        tableArticle.column('Statut:name').visible(false);
                    }

                    return json.data;
                }
            },
            ...referenceFilter
                ? {
                    search: {
                        search: referenceFilter,
                    },
                }
                : {},
            columns,
            drawConfig: {
                needsResize: true
            },
            drawCallback: () => {
                const datatable = $(`#tableArticle_id`).DataTable();
                togglePrintButton(datatable, $(`.printButton`), () => datatable.search());
            },
            rowConfig: {
                needsRowClickAction: true
            },
        };
        tableArticle = initDataTable('tableArticle_id', tableArticleConfig);
        init();
    });
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

    let $modalDeleteArticle = $("#modalDeleteArticle");
    let $submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('article_delete', true);
    InitModal($modalDeleteArticle, $submitDeleteArticle, urlDeleteArticle, { tables: [tableArticle] });
}

function printArticlesBarCodes($button, event) {
    let templates;
    try {
        templates = JSON.parse($('#tagTemplates').val());
    } catch (error) {
        templates = [];
    }
    if (!$button.hasClass('dropdown-item') || !$button.hasClass('disabled')) {
        let listArticles = $('.article-row-id')
            .map((_, element) => $(element).val())
            .toArray();

        if (tableArticle.page.info().length > 0) {
            const params = {
                listArticles: listArticles,
            };
            if (templates.length > 0) {
                Promise.all(
                    [AJAX.route('GET', `article_print_bar_codes`, {forceTagEmpty: true, ...params}).file({})]
                        .concat(templates.map(function(template) {
                            params.template = template;
                            return AJAX
                                .route('GET', `article_print_bar_codes`, params)
                                .file({})
                        }))
                ).then(() => Flash.add('success', 'Impression des étiquettes terminée.'));
            } else {
                window.location.href = Routing.generate(
                    'article_print_bar_codes',
                    params,
                    true
                );
            }
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
