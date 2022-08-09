$(function () {
    const $languageSelect = $('#utilisateur_language');
    const $dataFormatSelect = $('#utilisateur_dateFormat');

    $languageSelect.select2({
        minimumResultsForSearch: -1,
        templateResult: format,
        templateSelection: format,
    })
    $dataFormatSelect.select2({
        minimumResultsForSearch: -1,
    })
});

function format(state) {
    const $option = $(state.element)
    return $(`
        <span class="d-flex align-items-center">
            <img src="${$option.data('flag')}" width="20px" height="20px" class="round mr-2"/>
            ${state.text}
        </span>
    `);
}
