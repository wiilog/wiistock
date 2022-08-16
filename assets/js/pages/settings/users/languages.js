

$(function () {
    const $flagFile = $("input[name='customFlag']");
    const $flagPreview = $(".custom-flag-preview");
    const $languageSelect = $("div[data-name='language']");
    const $languageTo = $(".language-to");

    // show new language form on check of the input and generate translations
    getTranslations($languageSelect.find(':checked').val());
    $languageSelect.on('change', function () {
        const newRadio = $(this).find('input[value=\'NEW\']')
        if (newRadio.is(':checked')) {
            $("#new-language").removeClass('d-none');
            // set a empty image in the selector
            $flagPreview.attr('src', 'data:image/svg+xml;charset=utf8,%3Csvg%20xmlns=\'http://www.w3.org/2000/svg\'%3E%3C/svg%3E');
        }
        else {
            $("#new-language").addClass('d-none');
        }
        getTranslations($(this).find(':checked').val());
    });


    $("input[name='newLanguage']").on('keyup', function () {
        const $languageTo = $(".language-to");
        $languageTo.text($(this).val());
    });

    // select flag in new language
    $('.select').on('click', function () {
        const $flag = $(this)
        $(".custom-flag-preview").attr('src', $flag.attr('src'));
    });
    $(".add-new-flag").on('click', function () {
        $flagFile.click();
    });
    $flagFile.on('change', function () {
        updateImagePreview($(".custom-flag-preview"), $flagFile)
    });

    // change the default language and reload the page
    $("div[data-name='defaultLanguages']").on('change', function () {
        $.get(Routing.generate(`settings_defaultLanguage_api`, {language: $(this).find(':checked').val()}, true))
            .then(() => {
                window.location.reload();
            });
    });

    //save translations
    $("button[name='save-translations']").on('click', function () {
        //get all translations that are modified
        const $translations =$("input[data-name='input-translation'][data-is-modify=\"1\"]");
        const translations = [];
        $translations.each(function () {
            translations.push({
                id: $(this).data('translation-id'),
                value: $(this).val()
            })
        });

        const language = $("div[data-name='language']").find(':checked').val();
        const languageName = $("input[name='newLanguage']").val();
        const languageFlag = $flagPreview.attr('src');

        console.log(language, languageName, languageFlag, translations);

        //send them to the server
        $.get(Routing.generate(`settings_language_save_api`, {translations : JSON.stringify(translations), language, languageName, languageFlag}, true))
            .then(() => {
                window.location.reload();
            });
    });
});

function getTranslations(language) {
    const $categoriesNavbar = $("#categoriesNavbar")
    $categoriesNavbar.find('.selected').removeClass('selected');
    $categoriesNavbar.find(':first-child').addClass('selected');


    $.get(Routing.generate(`settings_language_api`, {language}, true))
        .then(({template}) => {
            const $translationsContainer = $(`.translations-container`);
            $translationsContainer.html(template);
            // delete language
            $("button[name='deleteLanguage']").on('click', function () {
                const language = $("div[data-name='language']").find(':checked').val();
                $.get(Routing.generate(`settings_language_delete_api`, {language}, true))
                    .then(() => {
                        window.location.reload();
                    });
            });

            // mark translations as modified
            $("input[data-name='input-translation']").on('change',function () {
                $(this).attr('data-is-modify', '1');
            });
        });
}
