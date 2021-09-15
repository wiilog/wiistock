import '../scss/article_reference.scss';
import {initEditor} from './utils';

$(document).ready(() => {
    initEditor(`.editor-container`);

    $(`.add-supplier-article`).click(function() {
        console.log("ok", $(this).siblings(`.supplier-articles`))
        $(this).siblings(`.supplier-articles`).append($(`#supplier-article-template`).html());
    });

    $(document).on(`click`, `.delete-supplier-article`, function() {
        $(this).closest(`.ligneFournisseurArticle`).remove();
    });

    $(`.save`).click(function() {
        const $button = $(this);
        processSubmitAction($(`.ra-form`), $button, $button.data(`submit`), {
            onSuccess: () => window.location.href = Routing.generate('reference_article_index')
        });
    });

    $('#upload-article-reference-image').change(() => {
        const $uploadArticleReferenceImage = $('#upload-article-reference-image')[0];
        if ($uploadArticleReferenceImage.files && $uploadArticleReferenceImage.files.length > 0) {
            if($uploadArticleReferenceImage.files[0].size > MAX_UPLOAD_FILE_SIZE) {
                showBSAlert(`La taille de l'image ne peut excéder 10mo`, `warning`);
            } else {
                updateArticleReferenceImage($('.image-container'), $uploadArticleReferenceImage);
            }
        }
    });

    $('.delete-image').click(() => {
        const $imageContainer = $('.image-container');
        const $defaultImage = $('#default-image');

        $('#upload-article-reference-image').val(null);
        $imageContainer.css('background-image', "url(" + $defaultImage.val() + ")");
        $imageContainer.css('background-color', '#F5F5F7');
        $imageContainer.css('background-size', '100px');
    });

    $(document).on(`click`, `.increase-decrease-field .increase, .increase-decrease-field .decrease` , function(){
        updateInputValue($(this));
    });
});

function updateArticleReferenceImage($div, $image) {
    const reader = new FileReader();
    reader.readAsDataURL($image.files[0]);
    reader.onload = () => {
        const $imageContainer = $('.image-container');
        $imageContainer.css('background-image', "url(" + reader.result + ")");
        $imageContainer.css('background-color', '#FFFFFF');
        $imageContainer.css('background-size', 'cover');
    }
}

function updateInputValue($button) {
    const $input = $button.siblings('input').first();
    const value = parseInt($input.val());
    if($button.hasClass('increase')){
        $input.val(value+1);
    } else if($button.hasClass('decrease') && value !== 1) {
        $input.val(value-1);
    }

    $input.trigger(`change`);
}
