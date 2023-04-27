import {GET, POST} from "@app/ajax";

let tables = [];
const requestId = $('#demande-id').val();

global.ajaxGetAndFillArticle = ajaxGetAndFillArticle;
global.deleteRowDemande = deleteRowDemande;
global.validateLivraison = validateLivraison;
global.ajaxEditArticle = ajaxEditArticle;
global.removeLogisticUnitLine = removeLogisticUnitLine;
global.initDeliveryRequestModal = initDeliveryRequestModal;
global.openAddLUModal = openAddLUModal;

$(function () {
    $('.select2').select2();
    initDateTimePicker();
    Select2Old.user('Utilisateurs');
    Select2Old.articleReference($('.ajax-autocomplete'), {
        minQuantity: Number($('input[name=managePreparationWithPlanning]').val()) ? 0 : 1,
    });

    loadLogisticUnitList(requestId);
    initPageModals();

    const $submitNewArticle = $('#submitNewArticle');

    $submitNewArticle.on('click', function () {
        const $modal = $submitNewArticle.closest('.modal');
        const $articleSelect = $modal.find('#article');
        const $articleOptions = $articleSelect.children('option');

        if ($articleOptions.length === 1) {
            showBSAlert('Il n\'y a aucun article disponible pour cette référence.', 'danger')
        } else {
            return true;
        }
    });

    $(`#modalNewArticle`).on(`shown.bs.modal`, function () {
        clearModal('#modalNewArticle');
        $(this).find('#reference').select2("open");
    });

    const $editableTableArticles = $('#editableTableArticles');
    if ($editableTableArticles.length) {
        initEditableTableArticles($editableTableArticles);
    }
});

function loadLogisticUnitList(requestId) {
    const $logisticUnitsContainer = $('.logistic-units-container');
    wrapLoadingOnActionButton($logisticUnitsContainer, () => (
        AJAX
            .route('GET', 'delivery_request_logistic_units_api', {id: requestId})
            .json()
            .then(({html}) => {
                $logisticUnitsContainer.html(html);
                $logisticUnitsContainer
                    .find('.articles-container table')
                    .each(function () {
                        const $table = $(this);
                        const table = initDataTable($table, {
                            serverSide: false,
                            ordering: true,
                            paging: false,
                            searching: false,
                            processing: true,
                            order: [['reference', "desc"]],
                            columns: [
                                {data: 'Actions', title: '', className: 'noVis', orderable: false},
                                {data: 'reference', title: 'Référence'},
                                {data: 'barcode', title: 'Code barre'},
                                {data: 'label', title: 'Libellé'},
                                {data: 'location', title: 'Emplacement'},
                                {data: 'targetLocationPicking', title: 'Emplacement cible picking', visible: Number($(`input[name=showTargetLocationPicking]`).val())},
                                {data: 'quantityToPick', title: 'Quantité à prélever'},
                                {data: 'error', title: 'Erreur', visible: false},
                            ],
                            rowConfig: {
                                needsRowClickAction: true,
                                needsColor: true,
                                color: 'danger',
                                dataToCheck: 'error'
                            },
                            domConfig: {
                                removeInfo: true,
                            },
                        });

                        tables.push(table);
                    });
            })
        )
    );
}

function getCompareStock(submit) {
    let path = Routing.generate('compare_stock', true);
    let params = {
        'demande': submit.data('id'),
        'fromNomade': false
    };

    return $.post({
            url: path,
            dataType: 'json',
            data: JSON.stringify(params)
        })
        .then(function (response) {
            if (response.success) {
                $('.zone-entete').html(response.entete);
                $('#boutonCollecteSup, #boutonCollecteInf').addClass('d-none');
                loadLogisticUnitList(requestId);
            } else {
                showBSAlert(response.msg, 'danger');
            }
        });
}

function ajaxGetAndFillArticle($select) {
    if ($select.val() !== null) {
        let path = Routing.generate('demande_article_by_refArticle', true);
        let refArticle = $select.val();
        const deliveryRequestId = $('[name="delivery-request-id"]').val();
        let params = {
            refArticle: refArticle,
            deliveryRequestId: deliveryRequestId
        };
        let $selection = $('#selection');
        let $editNewArticle = $('#editNewArticle');
        let $modalFooter = $('#modalNewArticle').find('.modal-footer');

        $selection.html('');
        $editNewArticle.html('');
        $modalFooter.addClass('d-none');

        $.post(path, JSON.stringify(params), function (data) {
            $selection.html(data.selection);
            $editNewArticle.html(data.modif);
            $modalFooter.removeClass('d-none');
            toggleRequiredChampsLibres($('#typeEdit'), 'edit');
            Select2Old.location($editNewArticle.find('.ajax-autocomplete-location-edit'));
            Select2Old.user($editNewArticle.find('.ajax-autocomplete-user-edit[name=managers]'));

            setMaxQuantity($select);
        }, 'json');
    }
}

function setMaxQuantity(select) {
    let params = {
        refArticleId: select.val(),
    };
    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        if (data) {
            let modalBody = select.closest(".modal-body");
            modalBody.find('#quantity-to-deliver').attr('max', data);
        }
    }, 'json');
}

function deleteRowDemande(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}

function validateLivraison(livraisonId, $button) {
    let params = JSON.stringify({id: livraisonId});

    wrapLoadingOnActionButton($button, () => (
        $.post({
                url: Routing.generate('demande_livraison_has_articles'),
                data: params
            })
            .then(function (resp) {
                if (resp === true) {
                    return getCompareStock($button);
                } else {
                    $('#cannotValidate').click();
                    return false;
                }
            })
    ));
}

function ajaxEditArticle(select) {
    let path = Routing.generate('article_show', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function (data) {
        if (data) {
            $('#editNewArticle').html(data);
            let quantityToTake = $('#quantityToTake');
            let valMax = $('#quantite').val();

            if (valMax) {
                quantityToTake.find('input').attr('max', valMax);
            }
            quantityToTake.removeClass('d-none');
            Select2Old.location($('.ajax-autocomplete-location-edit'));
            $('.list-multiple').select2();
            //WIIS-8166 open and close select2 Reference for fix a scrolling bug
            $('#reference').select2('open');
            $('#reference').select2('close');
        }
    });
}

function initPageModals() {
    let $modalNewArticle = $("#modalNewArticle");
    let $submitNewArticle = $("#submitNewArticle");
    let pathNewArticle = Routing.generate('demande_add_article', true);
    InitModal($modalNewArticle, $submitNewArticle, pathNewArticle, {
        success: () => loadLogisticUnitList(requestId)
    });

    let $modalDeleteArticle = $("#modalDeleteArticle");
    let $submitDeleteArticle = $("#submitDeleteArticle");
    let pathDeleteArticle = Routing.generate('demande_remove_article', true);
    InitModal($modalDeleteArticle, $submitDeleteArticle, pathDeleteArticle, {
        success: () => {
            loadLogisticUnitList(requestId);
            const $editableTableArticles = $('#editableTableArticles');
            $editableTableArticles.DataTable().ajax.reload();
        }
    });

    let $modalEditArticle = $("#modalEditArticle");
    let $submitEditArticle = $("#submitEditArticle");
    let pathEditArticle = Routing.generate('demande_article_edit', true);
    InitModal($modalEditArticle, $submitEditArticle, pathEditArticle, {
        success: () => loadLogisticUnitList(requestId)
    });

    let urlDeleteDemande = Routing.generate('demande_delete', true);
    let $modalDeleteDemande = $("#modalDeleteDemande");
    let $submitDeleteDemande = $("#submitDeleteDemande");
    InitModal($modalDeleteDemande, $submitDeleteDemande, urlDeleteDemande);
}

function initDeliveryRequestModal() {
    const $modal = $('#modalEditDemande');
    InitModal($modal, $('#submitEditDemande'), Routing.generate('demande_edit', true));
    toggleLocationSelect($modal.find('[name="type"]'));
}

function openAddLUModal() {
    const $modal = $(`#modalAddLogisticUnit`);
    const $details = $modal.find(`.modal-details`);
    const $submit = $modal.find(`[type="submit"]`);
    const $logisticUnit = $modal.find(`[name="logisticUnit"]`);

    $logisticUnit.off(`change`);
    $logisticUnit.val(null).empty().trigger(`change`);
    $details.html(``);
    $submit.prop(`disabled`, true);

    $modal.modal(`show`);

    $logisticUnit.on(`change`, function () {
        const logisticUnit = $logisticUnit.val();

        if (logisticUnit) {
            AJAX.route(`GET`, `delivery_logistic_unit_details`, {logisticUnit})
                .json()
                .then(({html}) => {
                    $details.html(html);
                    $submit.prop(`disabled`, false);
                });
        }
    });

    $submit
        .off(`click`)
        .on(`click`, function () {
            const delivery = $modal.data(`delivery`);
            const logisticUnit = $logisticUnit.val();

            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(`POST`, `delivery_add_logistic_unit`, {delivery, logisticUnit})
                    .json()
                    .then(result => {
                        if (result.success) {
                            $modal.modal(`hide`);
                            loadLogisticUnitList(requestId);

                            if (result.header) {
                                $(`.zone-entete`).html(result.header);
                            }
                        }
                    })
            ));
        });
}

function removeLogisticUnitLine($button, logisticUnitId) {
    wrapLoadingOnActionButton($button, () => (
        AJAX.route('POST', 'remove_delivery_request_logistic_unit_line', {logisticUnitId, deliveryRequestId: requestId})
            .json()
            .then(() => loadLogisticUnitList(requestId))
    ));
}

function initEditableTableArticles($table) {
    const form = JSON.parse($('input[name="editableTableArticlesForm"]').val());
    const fieldsParams = JSON.parse($('input[name="editableTableArticlesFieldsParams"]').val());

    const table = initDataTable($table, {
        serverSide: false,
        ajax: {
            type: "GET",
            url: Routing.generate('api_table_articles_content', {request: requestId}, true),
        },
        rowConfig: {
            needsRowClickAction: true,
        },
        domConfig: {
            removeInfo: true,
        },
        ordering: false,
        paging: false,
        searching: false,
        scrollY: false,
        scrollX: true,
        drawCallback: () => {
            $(`#packTable_wrapper`).css(`overflow-x`, `scroll`);
            $(`.dataTables_scrollBody, .dataTables_scrollHead`)
                .css('overflow', 'visible')
                .css('overflow-y', 'visible');

            const $rows = $(table.rows().nodes());

            $rows.each(function () {
                const $row = $(this);
                const data = Form.process($row, {
                    ignoreErrors: true,
                });

                $row.off('click.delete-row')
                    .on('click.delete-row', '.delete-row', function(){
                    if (!($(this).data('id'))){
                        const row = table.row($(this).closest(`tr`));
                        row.remove();
                        table.draw();
                    }
                });

                $row.data(`data`, JSON.stringify(data instanceof FormData ? data.asObject() : data));
            });

            $rows.off(`focusout.keyboardNavigation`).on(`focusout.keyboardNavigation`, function (event) {
                const $row = $(this);
                const $target = $(event.target);
                const $relatedTarget = $(event.relatedTarget);


                const wasLineSelect = $target.closest(`td`).find(`select[name="pack"]`).exists();
                if ((event.relatedTarget && $.contains(this, event.relatedTarget))
                    || $relatedTarget.is(`button.delete-row`)
                    || wasLineSelect) {
                    return;
                }

                saveArticleLine(requestId, $row);
            });

            scrollToBottom();
            if (!$table.data('initialized')) {
                $table.data('initialized', true);
                // Resize table to avoid bug related to WIIS-8276,
                // timeout is necessary because drawCallback doesnt seem to be called when everything is fully loaded,
                // because we have some custom rendering actions which may take more time than native datatable rendering
                setTimeout(() => {
                    $table.DataTable().columns.adjust().draw();
                }, 500);
            }
        },
        createdRow: (row, data) => {
            // we display only + td on this line
            if (data && data.createRow) {
                const $row = $(row);
                const $tds = $row.children();
                const $tdAction = $tds.first();
                const $tdOther = $tds.slice(1);

                $tdAction
                    .attr('colspan', $tds.length)
                    .addClass('add-row');
                $tdOther.addClass('d-none');
            }
        },
    });

    scrollToBottom();
    $table.on(`keydown`, `[name="quantity"]`, function (event) {
        if (event.key === `.` || event.key === `,` || event.key === `-` || event.key === `+` || event.key === `e`) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    $table.on(`click`, `.add-row`, function () {
        addArticleRow(table, $(this));
    });

    $table.off(`click.pointer`)
        .on(`click.pointer`, `.pointer`, function () {
            addArticleRow(table, $(this));
        });

    $table.on(`change`, `select[name="reference"]`, function () {
        const $row = $(this).closest(`tr`);
        const inputData = $(this).select2('data')[0];
        const label = inputData.label;
        const reference = inputData.text;
        const barCode = inputData.barCode;
        const refType = inputData.typeId;
        const typeQuantite = inputData.typeQuantite;
        const referenceArticle = Number($(this).val());

        $row.find('span.article-label').text(label);
        $row.find('span.article-barcode').text(barCode);

        const $articleSelect = $row.find('select[name="article"]');
        if ($articleSelect.exists()) {
            if(typeQuantite === 'article') {
                AJAX
                    .route(GET, 'api_articles-by-reference', {referenceArticle})
                    .json()
                    .then(({data}) => {
                        const articleSelect = $row.find('select[name="article"]')
                        data.forEach((article) => {
                            articleSelect.append(`<option value="${article.value}">${article.text}</option>`);
                        });
                    });
            } else {
                $articleSelect.closest('label').remove();
            }
        }
        if (!(typeQuantite === 'article')) {
            $row.find('select[name="targetLocationPicking"]').closest('label').remove();
        }

        // conditional display
        // Parametrage|Stock|Demande|Livraison-Champs Fixes
        Object.entries(fieldsParams).forEach(([field, value]) => {
            if (value.displayedUnderCondition) {
                const $field = $row.find(`[name="${field}"]`);
                if (!(value.conditionFixedField === 'Type Reference' && value.conditionFixedValue.includes(refType.toString()))) {
                    $field.closest('label').remove();
                    $field.remove();
                }
            }
        })

        $(this).select2('destroy').closest('label').addClass('d-none');
        $(this).closest('td').find('span.article-reference').text(reference);
        $(this).closest('td').find('input[name="referenceId"]').val(referenceArticle);
    });


    let $modalDeleteArticle = $("#modalDeleteArticle");

    $(window).on(`beforeunload`, () =>  {
        const $focus = $(`tr :focus`);
        if($focus.exists()) {
            if(savePackLine(dispatchId, $focus.closest(`tr`), false)) {
                return true;
            }
        }
    });
    return table;
}

function saveArticleLine(requestId, $row,) {
    let data = Form.process($row);
    data = data instanceof FormData ? data.asObject() : data;

    if (data) {
        if (!jQuery.deepEquals(data, JSON.parse($row.data(`data`)))) {
            AJAX
                .route(POST, `api_demande_article_submit_change`, {deliveryRequest : requestId})
                .json(data)
                .then((response) => {
                    if (response.success) {
                        if (response.lineId) {
                            $row.find(`.delete-row`).data(`id`, response.lineId);
                            $row.find('input[name="lineId"]').val(response.lineId);
                        }
                        if (response.type) {
                            $row.find('input[name="type"]').val(response.type);
                        }
                        $row.data(`data`, JSON.stringify(data));
                    }
                });
            }
            return true;
    } else {
        $row.find('.is-invalid').first().trigger('focus');
        return false;
    }
}

function scrollToBottom() {
    window.scrollTo(0, document.body.scrollHeight);
}

function addArticleRow(table, $button) {
    const $table = $button.closest('table');
    const $isInvalid = $table.find('.is-invalid');

    if ($isInvalid.length === 0) {
        const row = table.row($button.closest(`tr`));
        const data = row.data();

        row.remove();
        table.row.add(JSON.parse($(`input[name="editableTableArticlesForm"]`).val()));
        table.row.add(data);
        table.draw();

        scrollToBottom();
    }
}
