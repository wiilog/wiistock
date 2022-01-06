import '../../scss/pages/reference-article.scss';
import {initEditor} from '../utils';

$(document).ready(() => {
    initEditor(`.editor-container`);

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
                success: data => {
                    if (data.success) {
                        window.location.href = Routing.generate('reference_article_show_page', {id: data.data.id})
                    }
                    else if (data.draftDefaultReference) {
                        $('input[name="reference"]').val(data.draftDefaultReference);
                    }
                }
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
