import Routing from '@app/fos-routing';
import {initDataTable, extendsDateSort} from "@app/datatable";
import {togglePrintButton} from "@app/utils";

let $printTag;
let pageTables;

window.removeFilter = removeFilter;
window.displayFilterValue = displayFilterValue;
window.showItems = showItems;
window.printReferenceArticleBarCode = printReferenceArticleBarCode;
window.updateQuantity = updateQuantity;
window.initDatatableMovements = initDatatableMovements;
window.initDatatablePurchaseRequests = initDatatablePurchaseRequests;

$(function () {
    $('#modalNewFilter').on('hide.bs.modal', function(e) {
        const $modal = $(e.currentTarget);
        $modal.find('.input-group').html('');
        $modal.find('.valueLabel').text('');
    });

    $printTag = $('.printButton');

    updateFilters();

    let activeFilter;
    if ($('#filters').find('.filter').length <= 0) {
        $('#noFilters').removeClass('d-none');
        activeFilter = true;
    } else {
        activeFilter = false;
    }
    initTableRefArticle().then((table) => {
        initPageModals(table);
    });


    $('#modalShowMouvements, #modalShowPurchaseRequests')
        .on('hide.bs.modal', function () {
            $(this).find('table.tableItems').DataTable().destroy();
        })
        .on('click', '[data-purchase-request-id]', function () {
            window.open(Routing.generate('purchase_request_show', {id: $(this).data('purchase-request-id')}), '_blank');
        });
});

function initPageModals(table) {
    let modalDeleteRefArticle = $("#modalDeleteRefArticle");
    let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
    let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
    InitModal(modalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle, {tables: [table], clearOnClose: true});

    let modalNewFilter = $('#modalNewFilter');
    let submitNewFilter = $('#submitNewFilter');
    let urlNewFilter = Routing.generate('filter_ref_new', true);
    InitModal(modalNewFilter, submitNewFilter, urlNewFilter, {
        tables: [table],
        clearOnClose: true,
        success: displayNewFilter
    });
}

function initTableRefArticle() {
    let url = Routing.generate('ref_article_api', true);
    return $
        .post(Routing.generate('ref_article_api_columns'))
        .then(function (data) {
            const columns = data.columns;
            const search = data.search;
            const index = data.index;

            columns.forEach(ref => ref.title = Translation.of('Stock', 'Références', 'Général', ref.title));

            let tableRefArticleConfig = {
                processing: true,
                serverSide: true,
                paging: true,
                order: [[2, 'asc']],
                ajax: {
                    'url': url,
                    'type': 'POST',
                    'dataSrc': function (json) {
                        return json.data;
                    },
                },
                search: {
                    search
                },
                displayStart: index,
                length: 10,
                columns: columns,
                drawConfig: {
                    needsResize: true
                },
                drawCallback: () => {
                    const datatable = $(`#tableRefArticle`).DataTable();
                    togglePrintButton(datatable, $(`.printButton`), () => (
                        datatable.search()
                        || $(`#filters`).find(`.filter`).length > 0
                    ));
                },
                rowConfig: {
                    classField: 'colorClass',
                    needsRowClickAction: true
                },
                page: 'reference'
            };

            pageTables = initDataTable('tableRefArticle', tableRefArticleConfig);

            window.pageTables = pageTables;
            return pageTables;
        });
}

// affiche le filtre après ajout
function displayNewFilter(data) {
    $('#filters').append(data.filterHtml);
    if ($printTag.is('button')) {
        $printTag.addClass('btn-primary');
        $printTag.removeClass('btn-disabled');
        $printTag.addClass('pointer');
    } else {
        $printTag.removeClass('disabled');
        $printTag.addClass('pointer');
    }
    $('#noFilters').addClass('d-none');
    $printTag.removeClass('has-tooltip');
    $printTag.tooltip('dispose');
}

function removeFilter($button, filterId) {
    $.ajax({
        url: Routing.generate('filter_ref_delete', true),
        type: 'DELETE',
        data: {filterId},
        success: function(data) {
            if (data && data.success) {
                pageTables.clear();
                pageTables.ajax.reload();

                const $filter = $button.closest('.filter');
                $filter.tooltip('dispose');
                $filter.parent().remove();
            } else if (data.msg) {
                showBSAlert(data.msg, 'danger');
            }

        }
    });
}

// modale ajout d'un filtre, affichage du champ "contient" en fonction du champ sélectionné
function displayFilterValue(elem) {
    let type = elem.find(':selected').data('type');
    let val = elem.find(':selected').val();
    let modalBody = elem.closest('.modal-body');

    let label = '';
    let datetimepicker = false;
    switch (type) {
        case 'sync':
        case 'booleen':
            label = 'Oui / Non';
            type = 'checkbox';
            break;
        case 'number':
        case 'list':
        case 'list multiple':
            label = 'Valeur';
            break;
        case 'date':
            label = 'Date';
            type = 'text';
            datetimepicker = true;
            break;
        case 'datetime':
            label = 'Date et heure'
            type = 'text';
            datetimepicker = true;
            break;
        default:
            label = 'Contient';
    }

    // cas particulier de liste déroulante pour type
    if (type === 'list' || type === 'list multiple') {
        let params = {
            'value': val
        };
        $.post(Routing.generate('filter_ref_display_field_elements'), JSON.stringify(params), function (data) {
            modalBody.find('.input-group').html(data);
            $('.list-multiple').select2();
        }, 'json');
    } else {
        modalBody.find('.input-group').html('<input type="' + type + '" class="form-control cursor-default data needed ' + type + '" id="value" name="value">');
        if (datetimepicker) initDateTimePicker('#modalNewFilter .text');
    }

    elem.closest('.modal-body').find('.valueLabel').text(label);
}


function printReferenceArticleBarCode($button, event) {
    if (!$button.hasClass('disabled')) {
        if (pageTables.data().count() > 0) {
            let searchValue = $('#tableRefArticle_filter input').val();
            // remove white space at the start and end of the string
            searchValue = searchValue.replace(/^\s+|\s+$/g, '');
            window.location.href = Routing.generate(
                'reference_article_bar_codes_print',
                {
                    length: pageTables.page.info().length,
                    start: pageTables.page.info().start,
                    search: {"value": searchValue},
                },
                true
            );
        } else {
            showBSAlert('Les filtres et/ou la recherche n\'ont donnés aucun résultats, il est donc impossible de les imprimer.', 'danger');
        }
    } else {
        event.stopPropagation();
    }
}

function initDatatableMovements(referenceArticleId, $modal) {
    extendsDateSort('customDate');
    let pathRefMouvements = Routing.generate('ref_mouvements_api', {referenceArticle: referenceArticleId}, true);
    let tableRefMvtOptions = {
        ajax: {
            "url": pathRefMouvements,
            "type": "POST"
        },
        drawConfig: {
            needsResize: true
        },
        domConfig: {
            removeInfo: true,
        },
        columns: [
            {"data": 'Date', 'title': 'Date', 'type': 'customDate'},
            {"data": 'from', 'title': 'Issu de', className: 'noVis'},
            {"data": 'ArticleCode', 'title': 'Code article'},
            {"data": 'Quantity', 'title': 'Quantité'},
            {"data": 'Origin', 'title': 'Origine'},
            {"data": 'Destination', 'title': 'Destination'},
            {"data": 'Type', 'title': 'Type'},
            {"data": 'Operator', 'title': 'Opérateur'}
        ],
    };
    initDataTable($modal.find('.tableItems'), tableRefMvtOptions);
}

function initDatatablePurchaseRequests(referenceArticleId, $modal) {
    extendsDateSort('customDate');
    let pathRefShippingRequest = Routing.generate('ref_purchase_requests_api', {referenceArticle: referenceArticleId}, true);
    let tableRefPurchaseRequestsOptions = {
        ajax: {
            "url": pathRefShippingRequest,
            "type": "POST"
        },
        serverSide: true,
        drawConfig: {
            needsResize: true
        },
        domConfig: {
            removeInfo: true,
        },
        columns: [
            {"data": 'creationDate', 'title': 'Date de création', 'type': 'customDate'},
            {"data": 'from', 'title': 'Numéro', sortable: false},
            {"data": 'requester', 'title': 'Demandeur'},
            {"data": 'status', 'title': 'Statut'},
            {"data": 'requestedQuantity', 'title': 'Quantité demandée'},
            {"data": 'orderedQuantity', 'title': 'Quantité commandée'},
        ],
    };
    initDataTable($modal.find('.tableItems'), tableRefPurchaseRequestsOptions);
}

function showItems(button, $modal, initDatatable) {
    const refId = button.data('id');
    const refLabel = button.data('ref-label');
    $modal.find('.ref-label').html(refLabel);
    initDatatable(refId, $modal);
    $modal.modal('show');
}

function updateQuantity(referenceArticleId) {
    let path = Routing.generate('update_qte_refarticle', {referenceArticle: referenceArticleId}, true);
    $.ajax({
        url: path,
        type: 'patch',
        dataType: 'json',
        success: (response) => {
            if (response.success) {
                pageTables.ajax.reload();
                showBSAlert('Les quantités de la réference article ont bien été recalculées.', 'success');
            } else {
                showBSAlert('Une erreur lors du calcul des quantités est survenue', 'danger');
            }
        }
    });
}

function updateFilters() {
    $('#filters .filter-parent[data-removable="1"]').remove();
    $.get(Routing.generate('filter_ref_update'))
        .then(({templates}) => {
            if (templates.length === 0) {
                $('.printButton').addClass('disabled');
            } else {
                templates.forEach(function (template) {
                    displayNewFilter(template);
                });
            }
        });
}
