import '@styles/details-page.scss';
import AJAX from "@app/ajax";
import {computeDescriptionFormValues, computeDescriptionShowValues} from "./common";

window.onTypeQuantityChange = onTypeQuantityChange;
window.toggleEmergency = toggleEmergency;
window.changeNewReferenceStatus = changeNewReferenceStatus;

$(document).ready(() => {
    const $periodSwitch = $('input[name="period"]');

    $periodSwitch.on('click', function() {
        buildQuantityPredictions($(this).val());
    })

    buildQuantityPredictions();

    $(`.add-supplier-article`).click(function() {
        $(this).siblings(`.supplier-articles`).append($(`#supplier-article-template`).html());
    });

    $(document).on(`click`, `.delete-supplier-article`, function() {
        $(this).closest(`.ligneFournisseurArticle`).remove();
    });

    $(`#touch`).change(function() {
        $(this).closest(`.details-page-dropdown`).find(`.dropdown-wrapper`).toggleClass(`open`)
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
                    window.location.href = data.redirect || Routing.generate('reference_article_show_page', {id: data.data.id});
                },
            }).then((data) => {
                if (data && typeof data === "object" && !data.success && data.draftDefaultReference) {
                    $('input[name="reference"]').val(data.draftDefaultReference);
                }
            });
        }
    });

    const $deleteImage = $('.delete-image');
    $('.edit-image').on('click', () => {
        $('#upload-article-reference-image').trigger('click');
    });

    $deleteImage.on('click', () => {
        $deleteImage.addClass('d-none');
        const $imageContainer = $('.image-container');
        const $defaultImage = $('#default-image');

        $('input[name=deletedImage]').val(1);
        $('#upload-article-reference-image').val(null);
        $imageContainer.css('background-image', "url(" + $defaultImage.val() + ")");
        $imageContainer.css('background-color', '#F5F5F7');
        $imageContainer.css('background-size', '100px');
    });

    $('#upload-article-reference-image').on('change', () => {
        const $uploadArticleReferenceImage = $('#upload-article-reference-image')[0];
        if ($uploadArticleReferenceImage.files && $uploadArticleReferenceImage.files.length > 0) {
            $deleteImage.removeClass('d-none');
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

    $(`input[name=length], input[name=width], input[name=height]`).on(`input`, () => {
        computeDescriptionFormValues({
            $length: $(`input[name=length]`),
            $width: $(`input[name=width]`),
            $height: $(`input[name=height]`),
            $volume: $(`input[name=volume]`),
            $size: $(`input[name=size]`),
        });
    });
});

function buildQuantityPredictions(period = 1) {
    const $id = $('input[name="reference-id"]')
    if ($id.length > 0) {
        AJAX.route('GET', 'reference_article_quantity_variations', {id: $id.val(), period})
            .json()
            .then(({data}) => {
                const chartValues = Object.values(data).map(({quantity}) => quantity);
                const tooltips = buildTooltipsForQuantityPredictions(data);
                const $element = $('#quantityPrevisions');
                initSteppedLineChart($element, Object.keys(data), chartValues, tooltips, 'Quantité en stock');
            })
    }
}

function buildTooltipsForQuantityPredictions(data) {
    const tooltips = {};
    Object.keys(data).forEach((key) => {
        let {preparations, receptions} = data[key];
        let tooltip = '';
        if (preparations > 0) {
            tooltip = preparations + ' préparation(s)';
        }
        if (receptions > 0) {
            tooltip += "\n" + receptions + ' réception(s)';
        }
        tooltips[key] = tooltip.split("\n").filter((element) => element !== '');
    })
    return tooltips;
}

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

function toggleEmergency($switch) {
    const $emergencyComment = $('.emergency-comment');
    if ($switch.is(':checked')) {
        $emergencyComment.removeClass('d-none');
    } else {
        $emergencyComment.addClass('d-none');
        $emergencyComment.val('');
    }
}

function onTypeQuantityChange($input) {
    toggleRequiredChampsFixes($input, '.wii-form');
    updateQuantityDisplay($input, '.wii-form');
    changeNewReferenceStatus($('[name=statut]:checked'));
}

function changeNewReferenceStatus($select){
    if ($select.exists()) {
        const draftStatusName = $(`input[name="draft-status-name"]`).val();
        const draftSelected = $select.val() === draftStatusName;

        const $reference = $(`input[name="reference"]`);
        const $quantite = $(`input[name="quantite"]`);
        const $location = $(`select[name="emplacement"]`);

        $quantite.prop(`disabled`, draftSelected);
        $reference.prop(`disabled`, draftSelected);
        $location.prop('disabled', draftSelected);

        if ($location.exists()) {
            $location.prop(`disabled`, draftSelected);
        }

        if (draftSelected) {
            const defaultDraftReference = $reference.data('draft-default');

            if (defaultDraftReference) {
                $reference.val(defaultDraftReference);
                $quantite.val(0);
            }

            $location.exists()

            if ($location.exists()) {
                const optionValue = $location.data('draft-default-value');
                const optionText = $location.data('draft-default-text');
                if (optionValue && optionText) {
                    const existing = $location.find(`option[value="${optionValue}"]`).exists();
                    if (existing) {
                        $location
                            .val(optionValue)
                            .trigger('change');
                    } else {
                        $location
                            .append(new Option(optionText, optionValue, true, true))
                            .trigger('change');
                    }
                }
            }
        }
    }
}

