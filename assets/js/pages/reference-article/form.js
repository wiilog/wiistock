import '@styles/details-page.scss';
import AJAX, {GET, POST} from "@app/ajax";
import {computeDescriptionFormValues} from "./common";
import Form from "@app/form";
import Routing from '@app/fos-routing';

global.onTypeQuantityChange = onTypeQuantityChange;
global.toggleEmergency = toggleEmergency;
global.changeNewReferenceStatus = changeNewReferenceStatus;
global.onTypeSecurityChange = onTypeSecurityChange;

global.onReferenceChange = onReferenceChange;
global.onLabelChange = onLabelChange;

$(document).ready(() => {
    const $periodSwitch = $('input[name="period"]');
    handleNeededFileSheet();

    const requestQuery = GetRequestQuery();
    const redirectRoute = requestQuery['redirect-route'];
    let redirectRouteParams;
    try {
        redirectRouteParams = requestQuery['redirect-route-params']
            ? JSON.parse(requestQuery['redirect-route-params'])
            : undefined;
    } catch (_) {
        delete requestQuery['redirect-route-params'];
        SetRequestQuery(requestQuery);
        redirectRouteParams = redirectRouteParams || {};
    }

    const referenceArticleId = $('input[name="reference-id"]').val();
    const $stockForecastContainer = $(".stock-forecast-container");
    const $stockForecastShowModal = $("#modalShowStockForecast");
    const $getStockForecastButton = $(".btn-get-stock-forecast");

    $getStockForecastButton.on("click", function () {
        wrapLoadingOnActionButton($getStockForecastButton, () => (
        AJAX.route(
            GET,
            "reference_article_get_stock_forecast",
            {
                referenceArticle: referenceArticleId
            }
        ).json().then(({html, success, msg}) => {
                if (success) {
                    $stockForecastContainer.html(html)
                    $stockForecastShowModal.modal("show");
                } else {
                    $stockForecastShowModal.modal("hide");
                }
            }
        )))
    })

    $periodSwitch.on('click', function () {
        buildQuantityPredictions($(this).val());
    })

    buildQuantityPredictions();

    $(`.add-supplier-article`).click(function () {
        formAddLine($(this), `.supplier-articles`, `#supplier-article-template`);
    });

    $(document).on(`click`, `.delete-supplier-article`, function () {
        $(this).closest(`.ligneFournisseurArticle`).remove();
    });

    $(`.add-storage-rule`).click(function () {
        formAddLine($(this), `.storage-rules`, `#storage-rule-template`);
    });

    $(document).on(`click`, `.delete-storage-rule`, function () {
        $(this).closest(`.lineStorageRule`).remove();
    });

    $(`.touch`).change(function () {
        $(this).closest(`.details-page-dropdown`).find(`.dropdown-wrapper`).toggleClass(`open`)
    })

    const $form = $('[data-reference-article-form]');
    const submitRoute = $form.find('button[type="submit"]').data('submit');
    const submitParams = $form.find('button[type="submit"]').data('submit-params');
    Form
        .create($form)
        .addProcessor((data, errors, $form) => {
            if ($form.find('.supplier-container').length === 0 && $('.ligneFournisseurArticle').length === 0) {
                errors.push({
                    elements: [$form.find('[data-supplier-form]')],
                    message: `Un fournisseur minimum est obligatoire pour continuer`,
                    global: true,
                });
            }
        })
        .addProcessor((data, errors, $form) => {
            $form.find('.wii-switch').each(function () {
                const $this = $(this);
                $this.find('input[type="radio"]:checked').each(function () {
                    const $radio = $(this);
                    data.append($radio.attr('name'), $radio.val());
                });
            })
        })
        .addProcessor((data, errors, $form) => {
            Object.entries(submitParams || {}).forEach(([key, value]) => {
                data.append(key, value);
            });
        })
        .submitTo(POST, submitRoute, {
            success: (data) => {
                window.location.href = redirectRoute
                    ? Routing.generate(redirectRoute, redirectRouteParams)
                    : data.id
                        ? Routing.generate('reference_article_show_page', {id: data.id})
                        : Routing.generate('reference_article_index');
            },
        });

    $('[name=security]').on('change', function () {
        onTypeQuantityChange($(this))
    }).trigger('change');

    $('.btn.cancel').on('click', () => {
        window.location.href = Routing.generate( redirectRoute || 'reference_article_index', redirectRoute ? redirectRouteParams : undefined);
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
            if ($uploadArticleReferenceImage.files[0].size > MAX_UPLOAD_FILE_SIZE) {
                showBSAlert(`La taille de l'image ne peut excéder 10mo`, `danger`);
            } else {
                updateArticleReferenceImage($('.image-container'), $uploadArticleReferenceImage);
            }
        }
    });

    $('.delete-button-container').click(function () {
        const entityId = $(this).data('id');
        const $inputToUpdate = $($(this).data('input-to-update'));
        if ($inputToUpdate.val() === '') {
            $inputToUpdate.val(entityId);
        } else {
            $inputToUpdate.val($inputToUpdate.val() + ',' + entityId);
        }
        $(this).closest('.entity-container').remove();
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

    const $type = $('select[name=type].data');
    const preselectedTypeVal = $type.val();
    if(preselectedTypeVal){
        $type.trigger('change');
    }
});

function deleteLine($button, $inputToUpdate) {
    const entityId = $button.data('id');
    if ($inputToUpdate.val() === '') {
        $inputToUpdate.val(entityId);
    } else {
        $inputToUpdate.val($inputToUpdate.val() + ',' + entityId);
    }
    $(this).closest('.supplier-container').remove();
}

function formAddLine($button, container, template) {
    let $container = $button.siblings(container);
    let lastIndex = $container.children().last().data('multiple-object-index');
    let $storageRuleTemplate = $($(template).html());
    $storageRuleTemplate.data('multiple-object-index', lastIndex !== undefined ? lastIndex + 1 : 0);
    $container.append($storageRuleTemplate);
}

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
    const $emergencyQuantity = $('.emergency-quantity');
    if ($switch.is(':checked')) {
        $emergencyComment.removeClass('d-none');
        $emergencyQuantity.removeClass('d-none');
    } else {
        $emergencyComment.addClass('d-none');
        $emergencyQuantity.addClass('d-none');
        $emergencyComment.val('');
        $emergencyQuantity.val('');
    }
}

function onTypeQuantityChange($input) {
    toggleRequiredChampsFixes($input, '.wii-form');
    updateQuantityDisplay($input, '.wii-form');
    changeNewReferenceStatus($('[name=statut]:checked'));
}

function changeNewReferenceStatus($select) {
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

function onTypeSecurityChange($input) {
    changeNewReferenceStatus($input);
    handleNeededFileSheet();
    handleNeededInputs($input);
}
function handleNeededInputs($input){
    const requiredFields = $input.val() === '1' && $input.is(':checked');

    const $onuCodeInput = $('input[name=onuCode]');
    const $productClassInput = $('input[name=productClass]');
    $onuCodeInput.toggleClass('needed', requiredFields);
    $productClassInput.toggleClass('needed', requiredFields);

    const $onuCodeLabel = $onuCodeInput.parent().find('span');
    const $productClassLabel = $productClassInput.parent().find('span');
    const onuCodeLabelText = $onuCodeLabel.text().split("*")[0];
    const productClassLabelText = $productClassLabel.text().split("*")[0];

    if(requiredFields){
        $onuCodeLabel.text(onuCodeLabelText+'*');
        $productClassLabel.text(productClassLabelText+'*');
    } else {
        $onuCodeLabel.text(onuCodeLabelText.split('*')[0]);
        $productClassLabel.text(productClassLabelText.split('*')[0]);
    }
}

function handleNeededFileSheet(){
    //if radio yes is checked, sheet is needed
    const radioYesChecked = $('input[name=security]:checked').val();
    const inputRequired = $('input[name=isSheetFileNeeded]');

    const oldTextLabelRequired = $.trim($('span[title=Fiche]').text().split("*")[0]);
    const labelRequired = $('span[title=Fiche]')

    if(radioYesChecked === "1"){
        inputRequired.val(1);
        labelRequired.text(`${oldTextLabelRequired}*`)
    }else{
        inputRequired.val(0);
        labelRequired.text(oldTextLabelRequired)
    }
}

function onLabelChange(){
    const $referenceLabel = $('[name=libelle].data');
    const $articleSupplierLabel = $('[name=labelFournisseur].data');

    if($articleSupplierLabel.length === 1){
        $articleSupplierLabel.val($referenceLabel.val());
    }
}

function onReferenceChange(){
    const $referenceReference = $('[name=reference].data');
    const $articleSupplierReference = $('[name=referenceFournisseur].data');

    if($articleSupplierReference.length === 1){
        $articleSupplierReference.val($referenceReference.val());
    }
}
