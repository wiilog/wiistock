$(function (){
    $('.select2').select2();

    let pathAddArticle = Routing.generate('collecte_article_api', {'id': id}, true);
    let tableArticleConfig = {
        ajax: {
            "url": pathAddArticle,
            "type": "POST"
        },
        order: [['Référence', 'desc']],
        rowConfig: {
            needsRowClickAction: true,
        },
        columns: [
            {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Référence', 'title': 'Référence'},
            {"data": 'Libellé', 'title': 'Libellé'},
            {"data": 'Emplacement', 'title': 'Emplacement'},
            {"data": 'Quantité', 'title': 'Quantité'}
        ],
    };
    let tableArticle = initDataTable('tableArticle_id', tableArticleConfig);

    let modal = $("#modalNewArticle");
    let submit = $("#submitNewArticle");
    let url = Routing.generate('collecte_add_article', true);
    InitModal(modal, submit, url, {tables: [tableArticle]});

    let modalEditArticle = $("#modalEditArticle");
    let submitEditArticle = $("#submitEditArticle");
    let urlEditArticle = Routing.generate('collecte_edit_article', true);
    InitModal(modalEditArticle, submitEditArticle, urlEditArticle, {tables: [tableArticle]});

    let modalDeleteArticle = $("#modalDeleteArticle");
    let submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('collecte_remove_article', true);
    InitModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, {tables: [tableArticle]});

    let modalDeleteCollecte = $("#modalDeleteCollecte");
    let submitDeleteCollecte = $("#submitDeleteCollecte");
    let urlDeleteCollecte = Routing.generate('collecte_delete', true)
    InitModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte);

});

function ajaxGetCollecteArticle(select, collect) {
    let $selection = $('#selection');
    let $editNewArticle = $('#editNewArticle');
    let modalNewArticle = '#modalNewArticle';
    let data = {};
    data['referenceArticle'] = $(select).val();

    let path = Routing.generate('get_collecte_article_by_refArticle', {collect});
    let params = JSON.stringify(data);
    $.post(path, params).then((data) => {
        $selection.html(data.selection);
        if (data.modif) {
            $editNewArticle.html(data.modif);
            Wiistock.registerNumberInputProtection($editNewArticle.find('input[type="number"]'));
        }
        $(modalNewArticle).find('.modal-footer').removeClass('d-none');
        toggleRequiredChampsLibres(select.closest('.modal').find('#type'), 'edit');
        Select2Old.location($('.ajax-autocomplete-location-edit'));
        Select2Old.user($('.ajax-autocomplete-user-edit[name=managers]'));
        $('.list-multiple').select2();
    });
    $selection.html('');
    $editNewArticle.html('');
}

function validateCollecte(collecteId, $button) {
    let params = JSON.stringify({id: collecteId});

    wrapLoadingOnActionButton($button, () => (
        $.post({
            url: Routing.generate('demande_collecte_has_articles'),
            data: params
        })
            .then(function (resp) {
                if (resp === true) {
                    window.location.href = Routing.generate('ordre_collecte_new', {'id': collecteId});
                    return true;
                } else {
                    $('#cannotValidate').click();
                    return false;
                }
            })
    ), false);
}

function deleteRowCollecte(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}

function ajaxEditArticle (select) {
    let path = Routing.generate('article_show', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function(data) {
        $('#editNewArticle').html(data);
        Select2Old.location($('.ajax-autocomplete-location-edit'));
    }, 'json');
}

function initEditModal() {
    const $modalEditCollect = $('#modalEditCollecte');
    const type = $modalEditCollect.find(`select[name=type]`).val();
    const $restrictedResults = $modalEditCollect.find(`input[name=restrictedLocations]`);
    const $locationSelector = $modalEditCollect.find(`select[name=Pcollecte]`);

    Select2Old.init(
        $locationSelector,
        '',
        $restrictedResults.val() ? 0 : 1,
        {
            route: 'get_locations_by_type',
            param: {type}
        });

    InitModal($modalEditCollect, $('#submitEditCollecte'), Routing.generate('collecte_edit', true));
}
