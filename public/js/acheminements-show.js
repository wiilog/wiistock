$(function () {
    let modalModifyAcheminements = $('#modalEditAcheminements');
    let submitModifyAcheminements = $('#submitEditAcheminements');
    let urlModifyAcheminements = Routing.generate('acheminement_edit', true);
    InitialiserModal(modalModifyAcheminements, submitModifyAcheminements, urlModifyAcheminements);

    let modalDeleteAcheminements = $('#modalDeleteAcheminements');
    let submitDeleteAcheminements = $('#submitDeleteAcheminements');
    let urlDeleteAcheminements = Routing.generate('acheminement_delete', true);
    InitialiserModal(modalDeleteAcheminements, submitDeleteAcheminements, urlDeleteAcheminements);
});

function validateAcheminement(acheminementId, $button) {
    let params = JSON.stringify({id: acheminementId});

    wrapLoadingOnActionButton($button, () => (
        $.post({
            url: Routing.generate('demande_acheminement_has_packs'),
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
