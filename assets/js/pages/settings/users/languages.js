
const EMPTY_IMAGE = 'data:image/svg+xml;charset=utf8,%3Csvg%20xmlns=\'http://www.w3.org/2000/svg\'%3E%3C/svg%3E';

$(function () {
    const $flagFile = $("input[name='customFlag']");
    const $flagPreview = $(".custom-flag-preview");
    const $languageSelect = $("div[data-name='language']");
    const $languageNameInput = $("input[name='newLanguage']");

    const hash = window.location.hash;
    if (hash.includes('languageBeforeReload')) {
        const language = hash.split('=')[1];
        if (language==='NEW') {
            $languageSelect.children().eq($languageSelect.children().length-2).find('input').prop('checked', true);
        }
        else {
            $languageSelect.find(`input[value='${language}']`).prop('checked', true);
        }
        window.location.hash = 'languageBeforeReload=' +  $languageSelect.find(':checked').val();
    }

    // show new language form on check of the input and generate translations
    getTranslations($languageSelect.find(':checked').val());
    $languageSelect.on('change', function () {
        const newRadio = $(this).find('input[value=\'NEW\']')
        if (newRadio.is(':checked')) {
            $("#new-language").removeClass('d-none');
            // set a empty image in the selector
            $flagPreview.attr('src', EMPTY_IMAGE);
        }
        else {
            $("#new-language").addClass('d-none');
        }
        const language = $(this).find(':checked').val();
        window.location.hash = 'languageBeforeReload=' + language;
        getTranslations(language);
    });

    $languageNameInput.on('keyup', function () {
        const $languageTo = $(".language-to");
        $languageTo.text($(this).val());
    });

    // select flag in new language
    $('.select').on('click', function () {
        const $flag = $(this)
        if ($flag.hasClass('add-new-flag')) {
            $flagFile.click();
        }
        else {
            $(".custom-flag-preview").attr('src', $flag.attr('src'));
            $flagFile.val("");
        }
    });
    $flagFile.on('change', function () {
        if ($(this).val() !== '') {
            updateImagePreview($(".custom-flag-preview"), $flagFile);
        }
    });

    // change the default language and reload the page
    $("div[data-name='defaultLanguages']").on('change', function () {
        $.post(Routing.generate(`settings_default_language_api`),{language: $(this).find(':checked').val()})
            .then(() => {
                window.location.reload();
            });
    });

    //save translations
    $("button[name='save-translations']").on('click', function () {
        $languageNameInput.removeClass('is-invalid');
        $(".select-flag").removeClass('is-invalid');

        const languageName = $languageNameInput.val();
        const languageCustomFlag = $flagFile[0].files[0];
        const languageFlag = $(".custom-flag-preview").attr('src');
        const language = $("div[data-name='language']").find(':checked').val();

        if (language === 'NEW') {
            if (languageName === '') {
                $languageNameInput.addClass('is-invalid');
            }
            else if (languageCustomFlag === undefined && (languageFlag === EMPTY_IMAGE)) {
                $(".select-flag").addClass('is-invalid');
            }
            else {
                saveTranslations(language, languageName, languageCustomFlag, languageFlag)
            }
        }
        else {
            saveTranslations(language, languageName, languageCustomFlag, languageFlag)
        }
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

            $('[data-toggle="tooltip"]').tooltip();

            // delete language
            const $modaleDeleteTranslationSubmit = $("#submitDeleteLanguage");
            const $modaleDeleteTranslation = $("#modalDeleteLanguage");
            const urlDeleteModale =  Routing.generate("settings_language_delete_api", true);
            InitModal( $modaleDeleteTranslation, $modaleDeleteTranslationSubmit, urlDeleteModale, {success: () => {window.location.reload()}});
            $("button[name='deleteLanguage']").on('click', function () {
                $modaleDeleteTranslation.modal('show');
            });

            // mark translations as modified
            $("input[data-name='input-translation']").on('change',function () {
                $(this).attr('data-is-modify', '1');
            });
        });
}

function saveTranslations(language, languageName, languageCustomFlag, languageFlag) {
    //get all translations that are modified
    const $translations =$("input[data-name='input-translation'][data-is-modify='1']");
    const translations = [];
    $translations.each(function () {
        translations.push({
            id: $(this).data('translation-id'),
            value: $(this).val(),
            source : $(this).data('source')
        })
    });

    const formData = new FormData();
    formData.set('translations', JSON.stringify(translations));
    formData.set('language', language);
    formData.set('languageName', languageName);
    formData.append('flagCustom', languageCustomFlag);
    formData.set('flagDefault', languageFlag);

    $.ajax({
        url: Routing.generate('settings_language_save_api', true),
        data: formData,
        type: "post",
        contentType: false,
        processData: false,
        cache: false,
        dataType: "json",
        success: function (data) {
            window.location.reload();
        }
    });
}
