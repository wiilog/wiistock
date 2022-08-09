let tableArticle;

$(function () {
    $('.select2').select2();
    initDateTimePicker();
    Select2Old.user('Utilisateurs');
    Select2Old.articleReference($('.ajax-autocomplete'), {
        minQuantity: Number($('input[name=managePreparationWithPlanning]').val()) ? 0 : 1,
    });

    tableArticle = initPageDatatable();
    initPageModals(tableArticle);

    const $submitNewArticle = $('#submitNewArticle');

    $submitNewArticle.on('click', function() {

        const $modal = $submitNewArticle.closest('.modal');
        const $articleSelect = $modal.find('#article');
        const $articleOptions = $articleSelect.children('option');

        if ($articleOptions.length === 1) {
            showBSAlert( 'Il n\'y a aucun article disponible pour cette référence.', 'danger')
        } else {
            return true;
        }

    });

    $(`#modalNewArticle`).on(`shown.bs.modal`, function() {
        clearModal('#modalNewArticle');
        $(this).find('#reference').select2("open");
    });
});

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
                tableArticle.ajax.reload();
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
        }
    });
}

function redirectToArticlesList() {
    window.location.href = Routing.generate('reference_article_index');
}

function initPageModals(tableArticle) {
    let $modalNewArticle = $("#modalNewArticle");
    let $submitNewArticle = $("#submitNewArticle");
    let pathNewArticle = Routing.generate('demande_add_article', true);
    InitModal($modalNewArticle, $submitNewArticle, pathNewArticle, {tables: [tableArticle]});

    let $modalDeleteArticle = $("#modalDeleteArticle");
    let $submitDeleteArticle = $("#submitDeleteArticle");
    let pathDeleteArticle = Routing.generate('demande_remove_article', true);
    InitModal($modalDeleteArticle, $submitDeleteArticle, pathDeleteArticle, {tables: [tableArticle]});

    let $modalEditArticle = $("#modalEditArticle");
    let $submitEditArticle = $("#submitEditArticle");
    let pathEditArticle = Routing.generate('demande_article_edit', true);
    InitModal($modalEditArticle, $submitEditArticle, pathEditArticle, {tables: [tableArticle]});

    let urlDeleteDemande = Routing.generate('demande_delete', true);
    let $modalDeleteDemande = $("#modalDeleteDemande");
    let $submitDeleteDemande = $("#submitDeleteDemande");
    InitModal($modalDeleteDemande, $submitDeleteDemande, urlDeleteDemande);
}

function initPageDatatable() {
    let pathArticle = Routing.generate('demande_article_api', {id: $('#demande-id').val()}, true);
    let tableArticleConfig = {
        processing: true,
        order: [['reference', "desc"]],
        ajax: {
            "url": pathArticle,
            "type": "POST"
        },
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
        }
    };
    return initDataTable('table-lignes', tableArticleConfig);
}

function initDeliveryRequestModal() {
    const $modal = $('#modalEditDemande');
    InitModal($modal, $('#submitEditDemande'), Routing.generate('demande_edit', true));
    toggleLocationSelect($modal.find('[name="type"]'));
}
