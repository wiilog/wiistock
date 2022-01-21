let $printTag;
let pageTables;

$(function () {
    $('#modalNewFilter').on('hide.bs.modal', function(e) {
        const $modal = $(e.currentTarget);
        $modal.find('.input-group').html('');
        $modal.find('.valueLabel').text('');
    });

    $printTag = $('#printTag');

    updateFilters();

    let activeFilter;
    if ($('#filters').find('.filter').length <= 0) {
        $('#noFilters').removeClass('d-none');
        activeFilter = true;
    } else {
        activeFilter = false;
    }
    managePrintButtonTooltip(activeFilter, $printTag.is('button') ? $printTag.parent() : $printTag);
    initTableRefArticle().then((table) => {
        initPageModals(table);
    });
    displayActifOrInactif($('#toggleActivOrInactiv'), true);
    registerNumberInputProtection($('#modalNewRefArticle').find('input[type="number"]'));
    onTypeQuantityChange($('input[name="type_quantite"]:checked'));
    $('input[name="urgence"]:checked').trigger('change');
});

function redirectPaperclipClick(button) {
    const $actionOnClick = button.closest('.referenceRow').find('.action-on-click')
    $actionOnClick.trigger('click');
}

function initPageModals(table) {
    let modalRefArticleNew = $("#modalNewRefArticle");
    let submitNewRefArticle = $("#submitNewRefArticle");
    let urlRefArticleNew = Routing.generate('reference_article_new', true);
    InitModal(modalRefArticleNew, submitNewRefArticle, urlRefArticleNew, {tables: [table]});
    Select2Old.user(modalRefArticleNew.find('.ajax-autocomplete-user[name=managers]'));
    Select2Old.user(modalRefArticleNew.find('.ajax-autocomplete-user[name=buyer]'));

    let modalDeleteRefArticle = $("#modalDeleteRefArticle");
    let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
    let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
    InitModal(modalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle, {tables: [table], clearOnClose: true});

    let modalModifyRefArticle = $('#modalEditRefArticle');
    let submitModifyRefArticle = $('#submitEditRefArticle');
    let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
    InitModal(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle, {tables: [table], clearOnClose: true});
    Select2Old.user(modalModifyRefArticle.find('.ajax-autocomplete-user-edit'));

    let modalNewFilter = $('#modalNewFilter');
    let submitNewFilter = $('#submitNewFilter');
    let urlNewFilter = Routing.generate('filter_ref_new', true);
    InitModal(modalNewFilter, submitNewFilter, urlNewFilter, {
        tables: [table],
        clearOnClose: true,
        success: displayNewFilter
    });
}

function afterLoadingEditModal($button) {
    initRequiredChampsFixes($button);
}

function initTableRefArticle() {
    let url = Routing.generate('ref_article_api', true);
    return $
        .post(Routing.generate('ref_article_api_columns'))
        .then(function (data) {
            const columns = data.columns;
            const search = data.search;
            const index = data.index;
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
                    }
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
                rowConfig: {
                    classField: 'colorClass',
                    needsRowClickAction: true
                },
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableRefArticle_id_filter'
                },
                page: 'reference'
            };

            pageTables = initDataTable('tableRefArticle_id', tableRefArticleConfig);
            pageTables.on('responsive-resize', function () {
                resizeTable();
            });
            return pageTables;
        });
}

function resizeTable() {
    pageTables
        .columns.adjust()
        .responsive.recalc();
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
    managePrintButtonTooltip(false, $printTag.is('button') ? $printTag.parent() : $printTag);
    $('#noFilters').addClass('d-none');
    $printTag.removeClass('has-tooltip');
    $printTag.tooltip('dispose');
}

function removeFilter($button, filterId, triggersUncheck) {
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
                if (triggersUncheck) {
                    $('#toggleActivOrInactiv').attr('checked', false);
                }
                if ($('#filters').find('.filter').length <= 0) {
                    $('#noFilters').removeClass('d-none');
                    if ($('#tableRefArticle_id_filter input').val() === '') {
                        if ($printTag.is('button')) {
                            $printTag
                                .addClass('btn-disabled')
                                .removeClass('btn-primary');
                            managePrintButtonTooltip(true, $printTag.parent());
                        } else {
                            $printTag
                                .removeClass('pointer')
                                .addClass('disabled')
                                .addClass('has-tooltip');
                            managePrintButtonTooltip(true, $printTag);
                        }
                        $printTag.removeClass('d-none');
                    }
                }
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
        case 'booleen':
            label = 'Oui / Non';
            type = 'checkbox';
            break;
        case 'number':
        case 'list':
            label = 'Valeur';
            break;
        case 'list multiple':
            label = 'Contient';
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
        case 'sync':
            label = 'Oui / Non';
            type = 'checkbox';
            break;
        default:
            label = 'Contient';
    }

    // cas particulier de liste déroulante pour type
    if (type === 'list' || type === 'list multiple') {
        let params = {
            'value': val
        };
        $.post(Routing.generate('display_field_elements'), JSON.stringify(params), function (data) {
            modalBody.find('.input-group').html(data);
            $('.list-multiple').select2();
        }, 'json');
    } else {
        modalBody.find('.input-group').html('<input type="' + type + '" class="form-control cursor-default data needed ' + type + '" id="value" name="value">');
        if (datetimepicker) initDateTimePicker('#modalNewFilter .text');
    }

    elem.closest('.modal-body').find('.valueLabel').text(label);
}

let ajaxEditArticle = function ($select) {
    const selectVal = $select.val();
    if (selectVal) {
        let modalFooter = $select.closest('.modal').find('.modal-footer');
        let path = Routing.generate('article_show', true);
        let params = {id: $select.val(), isADemand: 1};

        $.post(path, JSON.stringify(params), function (data) {
            if (data) {
                const $editChampLibre = $('.editChampLibre');
                $editChampLibre.html(data);
                Select2Old.location($('.ajax-autocomplete-location-edit'));
                toggleRequiredChampsLibres($select.closest('.modal').find('#type'), 'edit');
                $('#quantityToTake').removeClass('d-none');
                modalFooter.removeClass('d-none');
                $editChampLibre.find('#quantite').attr('name', 'quantite');
                setMaxQuantityByArtRef($('#livraisonShow').find('#quantity-to-deliver'));
            }
        }, 'json');
        modalFooter.addClass('d-none');
    }
}

function deleteArticleFournisseur(button) {
    let sendArray = {};
    sendArray['articleF'] = $(button).data('value');
    sendArray['articleRef'] = $(button).data('title');

    let path = Routing.generate('ajax_render_remove_fournisseur', true);
    let params = JSON.stringify(sendArray);
    $.post(path, params).then((data) => {
        $('#articleFournisseursEdit').html(data);
    });
}

function passArgsToModal(button) {
    let path = Routing.generate('article_fournisseur_can_delete', true);
    let params = JSON.stringify({articleFournisseur: $(button).data('value')});
    $.post(path, params, function (response) {
        const $modalDeleteSupplier = $('#modalDeleteFournisseur');
        const $submitDeleteSupplier = $("#submitDeleteFournisseur");
        if (response) {
            $modalDeleteSupplier.find('.modal-body').html('Voulez-vous réellement supprimer le lien entre ce<br> fournisseur et cet article ? ');
            $submitDeleteSupplier.data('value', $(button).data('value'));
            $submitDeleteSupplier.data('title', $(button).data('title'));
            $modalDeleteSupplier.find('#submitDeleteFournisseur').removeClass('d-none');
        } else {
            $modalDeleteSupplier.find('.modal-body').html('Cet article fournisseur est lié à des articles<br> il est impossible de le supprimer');
            $modalDeleteSupplier.find('#submitDeleteFournisseur').addClass('d-none');
        }
    }, 'json');
}

function setMaxQuantityByArtRef(input) {
    let val = 0;
    val = $('#quantite').val();
    input.attr('max', val);
}

function initRequiredChampsFixes(button) {
    let params = {id: button.data('id')};
    let path = Routing.generate('get_quantity_type');

    $.post(path, JSON.stringify(params), function (data) {
        displayRequiredChampsFixesByTypeQuantiteReferenceArticle(data, button)
    }, 'json');
}

function printReferenceArticleBarCode($button, event) {
    if (!$button.hasClass('disabled')) {
        if (pageTables.data().count() > 0) {
            window.location.href = Routing.generate(
                'reference_article_bar_codes_print',
                {
                    length: pageTables.page.info().length,
                    start: pageTables.page.info().start,
                    search: $('#tableRefArticle_id_filter input').val()
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

function displayActifOrInactif(select, onInit) {
    if (select.length) {
        let donnees;
        if (select.is(':checked')) {
            donnees = 'actif';
        } else {
            donnees = 'consommé';
        }

        let params = {donnees: donnees};
        let path = Routing.generate('reference_article_actif_inactif');
        if (!onInit) {
            $.post(path, JSON.stringify(params), function () {
                updateFilters();
                pageTables.ajax.reload();
            });
        }
    }
}

function initDatatableMovements(referenceArticleId) {
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
    initDataTable('tableMouvements', tableRefMvtOptions);
}

function showRowMouvements(button) {

    let id = button.data('id');
    let params = JSON.stringify(id);
    let path = Routing.generate('ref_mouvements_list', true);
    let modal = $('#modalShowMouvements');

    $.post(path, params, function (data) {
        modal.find('.modal-body').html(data);
        initDatatableMovements(id);
    }, 'json');
}

function toggleEmergency($switch) {
    const $emergencyComment = $('.emergency-comment');
    if ($switch.is(':checked')) {
        $emergencyComment.removeClass('d-none');
    } else {
        $emergencyComment.addClass('d-none');
        $emergencyComment.val('');
    }
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
    $.get(Routing.generate('update_filters'))
        .then(({templates}) => {
            console.log(templates);
            if (templates.length === 0) {
                $('.printButton').addClass('disabled');
            } else {
                templates.forEach(function (template) {
                    displayNewFilter(template);
                });
            }
        });
}

function changeNewReferenceStatus($select){
    if ($select.exists()) {
        const draftStatusName = $(`input[name="draft-status-name"]`).val();
        const draftSelected = $select.val() === draftStatusName;

        const $reference = $(`input[name="reference"]`);
        const $quantite = $(`input[name="quantite"]`);
        const $location = $(`select[name="emplacement"]`);

        $quantite.prop(`disabled`, draftSelected);
        $reference.prop(`disabled`, draftSelected);
        $location.prop('disabled', draftSelected);

        if ($location.exists()) {
            $location.prop(`disabled`, draftSelected);
        }

        if (draftSelected) {
            const defaultDraftReference = $reference.data('draft-default');

            if (defaultDraftReference) {
                $reference.val(defaultDraftReference);
                $quantite.val(0);
            }

            $location.exists()

            if ($location.exists()) {
                const optionValue = $location.data('draft-default-value');
                const optionText = $location.data('draft-default-text');
                if (optionValue && optionText) {
                    const existing = $location.find(`option[value="${optionValue}"]`).exists();
                    if (existing) {
                        $location
                            .val(optionValue)
                            .trigger('change');
                    } else {
                        $location
                            .append(new Option(optionText, optionValue, true, true))
                            .trigger('change');
                    }
                }
            }
        }
    }

}

function onTypeQuantityChange($input) {
    toggleRequiredChampsFixes($input, '.wii-form');
    updateQuantityDisplay($input, '.wii-form');
    changeNewReferenceStatus($('[name=statut]:checked'));
}
