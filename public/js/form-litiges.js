function openNewLitigeModal($button) {
    const modalSelector = $button.data('target');
    clearModal(modalSelector);
    ajaxAutoArticlesReceptionInit($(modalSelector).find('.select2-autocomplete-articles'), $('#receptionId').val());
    // we select default litige
    const $modal = $(modalSelector);
    const $selectStatusLitige = $modal.find('#statutLitige');
    const $statutLitigeDefault = $selectStatusLitige.siblings('input[hidden][name="default-status"]');
    fillDemandeurField($modal);
    if ($statutLitigeDefault.length > 0) {
        const idSelected = $statutLitigeDefault.data('id');
        $selectStatusLitige
            .find(`option:not([value="${idSelected}"]):not([selected])`)
            .prop('selected', false);
        $selectStatusLitige
            .find(`option[value="${idSelected}"]`)
            .prop('selected', true);
    }
}

function initTableArticleLitige() {

    let pathArticleLitige = Routing.generate('article_litige_api', {litige: $('#litigeId').val()}, true);
    let tableArticleLitigeConfig = {
        ajax: {
            "url": pathArticleLitige,
            "type": "POST"
        },
        columns: [
            {"data": 'codeArticle', 'name': 'codeArticle', 'title': 'Code article'},
            {"data": 'status', 'name': 'status', 'title': 'Statut'},
            {"data": 'libelle', 'name': 'libelle', 'title': 'Libellé'},
            {"data": 'reference', 'name': 'reference', 'title': 'Référence article'},
            {"data": 'quantity', 'name': 'quantity', 'title': 'Quantité'}
        ],
        domConfig: {
            needsPartialDomOverride: true,
        },
        "paging": false,

    };
    return initDataTable('tableArticleInLitige', tableArticleLitigeConfig);
}
