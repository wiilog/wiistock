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
