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

