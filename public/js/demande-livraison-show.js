$(function () {
    $('.select2').select2();
    initDateTimePicker();
    initSelect2($('#statut'), 'Statuts');
    ajaxAutoRefArticleInit($('.ajax-autocomplete'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Utilisateurs');

    let table = initPageDatatable();
    initPageModals(table);
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
                $('.zone-entete').html(response.message);
                $('#boutonCollecteSup, #boutonCollecteInf').addClass('d-none');
                tableArticle.ajax.reload();
            } else {
                alertErrorMsg(response.message);
            }
        });
}

function ajaxGetAndFillArticle($select) {
    if ($select.val() !== null) {
        let path = Routing.generate('demande_article_by_refArticle', true);
        let refArticle = $select.val();
        let params = JSON.stringify(refArticle);
        let $selection = $('#selection');
        let $editNewArticle = $('#editNewArticle');
        let $modalFooter = $('#modalNewArticle').find('.modal-footer');

        $selection.html('');
        $editNewArticle.html('');
        $modalFooter.addClass('d-none');

        $.post(path, params, function (data) {
            $selection.html(data.selection);
            $editNewArticle.html(data.modif);
            $modalFooter.removeClass('d-none');
            toggleRequiredChampsLibres($('#typeEdit'), 'edit');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));

            setMaxQuantity($select);
            registerNumberInputProtection($selection.find('input[type="number"]'));
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

function ajaxEditArticle (select) {
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
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
            $('.list-multiple').select2();
        }
    });
}

function redirectToArticlesList() {
    window.location.href = Routing.generate('reference_article_index');
}

function initPageModals(tableArticle) {
    let urlEditDemande = Routing.generate('demande_edit', true);
    let $modalEditDemande = $("#modalEditDemande");
    let $submitEditDemande = $("#submitEditDemande");
    InitModal($modalEditDemande, $submitEditDemande, urlEditDemande);

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
        order: [[1, "desc"]],
        ajax: {
            "url": pathArticle,
            "type": "POST"
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis'},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'Libellé', 'title': 'Libellé'},
            {"data": 'Emplacement', 'title': 'Emplacement'},
            {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        columnDefs: [
            {
                orderable: false,
                targets: 0
            }
        ],
    };
    return initDataTable('table-lignes', tableArticleConfig);
}
