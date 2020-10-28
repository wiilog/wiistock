$('.select2').select2();

let pathAddArticle = Routing.generate('collecte_article_api', {'id': id}, true);
let tableArticleConfig = {
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    order: [[1, 'desc']],
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

let modalModifyCollecte = $('#modalEditCollecte');
let submitModifyCollecte = $('#submitEditCollecte');
let urlModifyCollecte = Routing.generate('collecte_edit', true);
InitModal(modalModifyCollecte, submitModifyCollecte, urlModifyCollecte);

function ajaxGetCollecteArticle(select) {
    let $selection = $('#selection');
    let $editNewArticle = $('#editNewArticle');
    let modalNewArticle = '#modalNewArticle';
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $selection.html(data.selection);
            if (data.modif) {
                $editNewArticle.html(data.modif);
                registerNumberInputProtection($editNewArticle.find('input[type="number"]'));
            }
            $(modalNewArticle).find('.modal-footer').removeClass('d-none');
            toggleRequiredChampsLibres(select.closest('.modal').find('#type'), 'edit');
            Select2.location($('.ajax-autocomplete-location-edit'));
            initEditor(modalNewArticle + ' .editor-container-edit');
            $('.list-multiple').select2();
        }
    }
    path = Routing.generate('get_collecte_article_by_refArticle', true);
    $selection.html('');
    $editNewArticle.html('');
    let data = {};
    data['referenceArticle'] = $(select).val();
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
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

let ajaxEditArticle = function (select) {
    let path = Routing.generate('article_show', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function(data) {
        $('#editNewArticle').html(data);
        Select2.location($('.ajax-autocomplete-location-edit'));
        initEditor('.editor-container-edit');
    }, 'json');
}
