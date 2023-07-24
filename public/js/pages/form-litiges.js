function openNewLitigeModal($button) {
    const modalSelector = $button.data('target');
    clearModal(modalSelector);
    Select2Old.articleReception($(modalSelector).find('.select2-autocomplete-articles'), $('#receptionId').val());
    // we select default litige
    const $modal = $(modalSelector);
    const $selectdisputeStatus = $modal.find('[name="disputeStatus"]');
    const $defaultDisputeStatus = $selectdisputeStatus.siblings('input[type="hidden"][name="default-status"]');
    fillDemandeurField($modal);
    if ($defaultDisputeStatus.length > 0) {
        const idSelected = $defaultDisputeStatus.data('id');
        $selectdisputeStatus
            .find(`option:not([value="${idSelected}"]):not([selected])`)
            .prop('selected', false);
        $selectdisputeStatus
            .find(`option[value="${idSelected}"]`)
            .prop('selected', true);
    }
}

function initTableArticleLitige() {

    let pathArticleLitige = Routing.generate('article_dispute_api', {dispute: $('#disputeId').val()}, true);
    let tableArticleLitigeConfig = {
        ajax: {
            "url": pathArticleLitige,
            "type": "POST"
        },
        columns: [
            {data: 'codeArticle', name: 'codeArticle', title: Translation.of('Qualité', 'Litiges', 'Code article')},
            {data: 'status', name: 'status', title: Translation.of('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Statut')},
            {data: 'libelle', name: 'libelle', title: Translation.of('Qualité', 'Litiges', 'Libellé')},
            {data: 'reference', name: 'reference', title: Translation.of('Qualité', 'Litiges', 'Référence article')},
            {data: 'quantity', name: 'quantity', title: Translation.of('Traçabilité', 'Général', 'Quantité')}
        ],
        domConfig: {
            needsPartialDomOverride: true,
        },
        "paging": false,

    };
    return initDataTable('tableArticleInLitige', tableArticleLitigeConfig);
}
