import {formatIconSelector} from "@app/form";

$(function () {
    const $languageSelect = $('#utilisateur_language');
    const $dataFormatSelect = $('#utilisateur_dateFormat');

    $languageSelect.select2({
        minimumResultsForSearch: -1,
        templateResult: formatIconSelector,
        templateSelection: formatIconSelector,
    })
    $dataFormatSelect.select2({
        minimumResultsForSearch: -1,
    })
});

