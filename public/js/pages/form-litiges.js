function initTableArticleLitige() {
    let pathArticleLitige = Routing.generate('dispute_article_api', {dispute: $('[name="disputeId"]').val()}, true);
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
