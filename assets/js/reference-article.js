import '../scss/article_reference.scss';
import {initEditor} from './utils';

$(document).ready(() => {
    initEditor(`.editor-container`);
    $('.list-multiple').select2();

    $(`.add-supplier-article`).click(function() {
        $(this).siblings(`.supplier-articles`).append($(`#supplier-article-template`).html());
    });

    $(document).on(`click`, `.delete-supplier-article`, function() {
        $(this).closest(`.ligneFournisseurArticle`).remove();
    });

    $(`#touch`).change(function() {
        $(this).closest(`.ra-dropdown`).find(`.dropdown-wrapper`).toggleClass(`open`)
    })

    $(`.save`).click(function() {
        if($('.supplier-container').length === 0 && $('.ligneFournisseurArticle').length === 0) {
            showBSAlert('Un fournisseur minimum est obligatoire pour continuer', 'danger');
        } else {
            const $button = $(this);
            const $form = $(`.wii-form`);
            clearFormErrors($form);
            processSubmitAction($form, $button, $button.data(`submit`), {
                success: data => window.location.href = Routing.generate('reference_article_show_page', {id: data.data.id})
            });
        }
    });

    $('.edit-image').click(() => $('#upload-article-reference-image').click());

    $('.delete-image').click(() => {
        const $imageContainer = $('.image-container');
        const $defaultImage = $('#default-image');

        $('#upload-article-reference-image').val(null);
        $imageContainer.css('background-image', "url(" + $defaultImage.val() + ")");
        $imageContainer.css('background-color', '#F5F5F7');
        $imageContainer.css('background-size', '100px');
    });

    $('#upload-article-reference-image').change(() => {
        const $uploadArticleReferenceImage = $('#upload-article-reference-image')[0];
        if ($uploadArticleReferenceImage.files && $uploadArticleReferenceImage.files.length > 0) {
            if($uploadArticleReferenceImage.files[0].size > MAX_UPLOAD_FILE_SIZE) {
                showBSAlert(`La taille de l'image ne peut excéder 10mo`, `danger`);
            } else {
                updateArticleReferenceImage($('.image-container'), $uploadArticleReferenceImage);
            }
        }
    });

    $('.delete-button-container').click(function() {
        const supplierArticleId = $(this).data('id');
        const $suppliersToRemove = $('#suppliers-to-remove');
        if($suppliersToRemove.val() === '') {
            $suppliersToRemove.val(supplierArticleId);
        } else {
            $suppliersToRemove.val($suppliersToRemove.val() + ',' + supplierArticleId);
        }
        $(this).closest('.supplier-container').remove();
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
    const value = parseInt($input.val()) || 0;
    if($button.hasClass('increase')){
        $input.val(value+1);
        $input.removeClass('is-invalid');
    } else if($button.hasClass('decrease') && value >= 1) {
        $input.val(value-1);
        $input.removeClass('is-invalid');
    }
    else {
        $input.val(0)
    }

    $input.trigger(`change`);
}
