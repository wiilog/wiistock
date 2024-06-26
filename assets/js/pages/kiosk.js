import '@styles/pages/kiosk.scss';
import AJAX, {GET, POST} from "@app/ajax";
import Routing from '@app/fos-routing';

let scannedReference = '';
const $modalInStockWarning = $("#modal-in-stock-warning");
let $errorMessage = $modalInStockWarning.find('#stock-error-message');
let originalMessage = $errorMessage.text();
const $referenceRefInput = $('input[name=reference-ref-input]');
const $referenceLabelInput = $('input[name=reference-label-input]');
const $modalGiveUpStockEntry = $("#modalGiveUpStockEntry");
const $modalArticleIsNotValid = $("#modalArticleIsNotValid");
const $modalPrintHistory = $("#modal-print-history");
const $modalInformation = $("#modal-information");
const $modalWaiting = $("#modal-waiting");
const {token} = GetRequestQuery();

$(function () {
    //In order to always have a valid session cookie, we make a call to the server to extend session lifetime.
    const sessionLifeTime = $("[name=maxSessionTime]").val()
    const pingInterval = ((sessionLifeTime/2) - 1 )* 60 * 1000;
    setInterval(() => {
        AJAX.route(GET, `api_ping`).json().then(()=> {})
    }, pingInterval > 60000 ? pingInterval : 60000 );

    if ($modalPrintHistory) {
        $('#openModalPrintHistory').on('click', function () {
            $modalPrintHistory.modal('show');
        });
    }

    if ($modalInformation) {
        $('#information-button').on('click', function () {
            $modalInformation.modal('show');
            $modalInformation.find('.bookmark-icon').removeClass('d-none');
        });
    }

    Select2Old.user($('[name=applicant]'), '', 3);
    Select2Old.user($('[name=follower]'), '', 3);

    $(document).on('keypress', (event) => {
        if ($('.page-content').hasClass('home')) {
            if (event.originalEvent.key === 'Enter') {
                $modalWaiting.modal('show');
                AJAX.route(GET, `reference_article_check_quantity`, {token, scannedReference})
                    .json()
                    .then(({exists, inStock, referenceForErrorModal, codeArticle}) => {
                        $modalWaiting.modal('hide');
                        if (exists && inStock) {
                            let $errorMessage = $modalInStockWarning.find('#stock-error-message');
                            $errorMessage.html(originalMessage
                                .replace('@reference', `<span class="bold">${referenceForErrorModal}</span>`)
                                .replace('@codearticle', `<span class="bold">${codeArticle}</span>`)
                            );
                            $modalInStockWarning.modal('show');
                            $modalInStockWarning.find('.bookmark-icon').removeClass('d-none');
                        } else {
                            window.location.href = Routing.generate('kiosk_form', {token, scannedReference});
                        }
                        scannedReference = '';
                    });
            } else {
                scannedReference += event.originalEvent.key;
            }
        }
    });

    $referenceRefInput.on('keypress keyup search', () => {
        $referenceLabelInput.val($referenceRefInput.val());
    });

    $('.button-next').on('click', function () {
        const $current = $(this).closest('.entry-stock-container').find('.active');
        const $buttonNextContainer = $(this).parent();
        const $timeline = $('.timeline-container');
        const $currentTimelineEvent = $timeline.find('.current');
        const $inputs = $current.find('input[required]');
        const $selects = $current.find('select.needed');

        $selects.each(function () {
            if ($(this).find('option:selected').length === 0) {
                $(this).parent().find('.select2-selection ').addClass('invalid');
            } else {
                $(this).parent().find('.select2-selection ').removeClass('invalid');
            }
        });

        $inputs.each(function () {
            if (!($(this).val())) {
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });

        if($current.hasClass('reference-container') && $current.find('.invalid').length === 0){
            const {token, scannedreference} = GetRequestQuery();
            $modalWaiting.modal('show');
            AJAX.route(GET, `reference_article_check_quantity`, {token, scannedReference: $referenceRefInput.val()})
                .json()
                .then(({exists, inStock, referenceForErrorModal, codeArticle}) => {
                    $modalWaiting.modal('hide');
                    if (exists && inStock) {
                        let $errorMessage = $modalInStockWarning.find('#stock-error-message');
                        $errorMessage.html(originalMessage
                            .replace('@reference', `<span class="bold">${referenceForErrorModal}</span>`)
                            .replace('@codearticle', `<span class="bold">${codeArticle}</span>`)
                        );
                        $modalInStockWarning.modal('show');
                        $modalInStockWarning.find('.bookmark-icon').removeClass('d-none');
                        $modalInStockWarning.find('button.outline').on('click', function () {
                            window.location.href = Routing.generate('kiosk_index', {token}, true);
                        });
                    } else if (scannedreference !== $referenceRefInput.val()) {
                        window.location.href = Routing.generate('kiosk_form', {
                            token,
                            scannedReference: $referenceRefInput.val(),
                            notExistRefresh: !exists,
                        });
                    } else {
                        $current.removeClass('active').addClass('d-none');
                        $($current.next()[0]).addClass('active').removeClass('d-none');
                        $currentTimelineEvent.removeClass('current');
                        $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');
                    }
                });
        }
        if ($current.find('.invalid').length === 0) {
            if ($($current.next()[0]).hasClass('summary-container')) {
                const $articleDataInput = $('input[name=reference-article-input]');

                wrapLoadingOnActionButton($(this), () => (
                    AJAX.route(POST, 'check_article_is_valid', {token, barcode: $articleDataInput.val() || null, referenceLabel : $referenceRefInput.val() })
                        .json()
                        .then(({success, fromArticlePage}) => {
                            if(success || !fromArticlePage){
                                $current.removeClass('active').addClass('d-none');
                                $($current.next()[0]).addClass('active').removeClass('d-none');
                                $currentTimelineEvent.removeClass('current');
                                $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');

                                $buttonNextContainer.removeClass('d-flex').addClass('d-none');
                                $('.give-up-button-container').removeClass('d-flex').addClass('d-none');
                                $('.summary-button-container').removeClass('d-none').addClass('d-flex');

                                //endroit où l'on rajoute toutes les infos dans les sections correspondantes pour le récapitulatif
                                $('.reference-reference').html($('input[name=reference-ref-input]').val());
                                if ($articleDataInput.val()) {
                                    $('.reference-article').html($articleDataInput.val());
                                } else {
                                    $('.field-article-title').removeClass('d-flex').addClass('d-none');
                                }

                                $('.reference-label').html($('input[name=reference-label-input]').val());

                                const $applicantInput = $('select[name=applicant] option:selected');
                                const $followerInput = $('select[name=follower] option:selected');

                                if ($applicantInput.val()) {
                                    $('.reference-managers').html(
                                        $followerInput.val() ?
                                            $applicantInput.text().concat(', ', $followerInput.text()) :
                                            $applicantInput.text());
                                }

                                let $freeFieldLabel = $('.free-field-label');
                                let $freeFieldInput = $freeFieldLabel.find('input');
                                let freeFieldLabelValue = $freeFieldInput.val()

                                // in case the free field have data-input-type = switch, show 'oui' or 'non' instead of the value of the input
                                if($freeFieldLabel.find('[type="radio"]').length > 0){
                                    /*
                                    In case of switch $freeFieldInput contains 2 inputs
                                    checkedId is the id of the input checked
                                    freeFieldLabelValue is the text of the label corresponding to the checked input
                                    */
                                    const checkedId = $freeFieldInput.filter(':checked').attr('id');
                                    freeFieldLabelValue = $freeFieldLabel.find(`label[for="${checkedId}"]`).text();
                                    if(!checkedId){
                                        freeFieldLabelValue = '-';
                                    }
                                }

                                // show value depend on the type of the input (params selected)
                                $('.reference-free-field').html(
                                    freeFieldLabelValue
                                    || $freeFieldLabel.find('textarea').val()
                                    || $freeFieldLabel.find('select').find('option:selected').text());

                                $('.reference-commentary').html($('input[name=reference-comment]').val() || '-');
                            }
                            else {
                                $modalArticleIsNotValid.modal('show');
                                $modalArticleIsNotValid.find('.bookmark-icon').removeClass('d-none');
                            }
                        })));
            }
            else {
                $current.removeClass('active').addClass('d-none');
                $($current.next()[0]).addClass('active').removeClass('d-none');
                $currentTimelineEvent.removeClass('current');
                $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');
            }
        }
    });

    $('.return-or-give-up-button').on('click', function () {
        const $current = $('.active')
        const $timeline = $('.timeline-container');
        const $currentTimelineEvent = $timeline.find('.current');

        if (!$current.hasClass('.reference-container') && $current.prev()[0]) {
            $currentTimelineEvent.addClass('future').removeClass('current');
            $($currentTimelineEvent.prev()[0]).addClass('current').removeClass('future');
            $current.removeClass('active').addClass('d-none');
            $($current.prev()[0]).addClass('active').removeClass('d-none');
        } else {
            $modalGiveUpStockEntry.modal('show');
            $modalGiveUpStockEntry.find('.bookmark-icon').removeClass('d-none');
        }
    });

    $('.give-up-button').on('click', function () {
        $modalGiveUpStockEntry.modal('show');
        $modalGiveUpStockEntry.find('.bookmark-icon').removeClass('d-none');
    });

    $('.edit-stock-entry-button').on('click', function () {
        const $current = $(this).closest('.entry-stock-container').find('.active');
        const $referenceContainer = $(this).closest('.entry-stock-container').find('.reference-container');
        const $buttonNextContainer = $('.button-next').parent();
        const $timelineSpans = $('.timeline-container span');
        $('.give-up-button-container').removeClass('d-none').addClass('d-flex');
        $('.summary-button-container').removeClass('d-flex').addClass('d-none');
        $buttonNextContainer.removeClass('d-none').addClass('d-flex');

        $timelineSpans.each(function () {
            $(this).removeClass('current').addClass('future');
        });

        $timelineSpans.first().removeClass('future').addClass('current');

        $current.removeClass('active').addClass('d-none');
        $referenceContainer.addClass('active').removeClass('d-none');
    });

    $('.validate-stock-entry-button').on('click', function () {
        const $entryStockContainer = $(this).closest(`.entry-stock-container`);
        const {success} = ProcessForm($entryStockContainer);

        if(success) {

            $modalWaiting.modal('show');
            const $freeFieldLabel = $('.free-field-label');
            const freeFieldValue = $freeFieldLabel.find('input').val()
                || $freeFieldLabel.find('textarea').val()
                || $freeFieldLabel.find('select').find('option:selected').data('label');
            const freeFieldId = $('input[name=free-field-id]').val();

            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(GET, 'entry_stock_validate', {
                    reference: $('input[name=reference-ref-input]').val(),
                    label: $('input[name=reference-label-input]').val(),
                    article: $('input[name=reference-article-input]').val() || null,
                    applicant: $('select[name=applicant] option:selected').val(),
                    follower: $('select[name=follower] option:selected').val(),
                    comment: $('input[name=reference-comment]').val(),
                    freeField: freeFieldId && freeFieldValue ? [freeFieldId, freeFieldValue] : [],
                    token,
                })
                    .json()
                    .then(({success, msg, barcodesToPrint, referenceExist, successMessage}) => {
                        $modalWaiting.modal('hide');
                        if (success) {
                            printLabelWithOptions(token, barcodesToPrint, false);

                            const $successPage = $('.success-page-container');
                            $('.main-page-container').addClass('d-none');

                            $('.go-home-button').on('click', function () {
                                window.location.href = Routing.generate('kiosk_index', {token}, true);
                            });

                            if (referenceExist) {
                                $('.print-again-button').addClass('d-none');
                                $('.article-entry-stock-success .field-success-page').html(successMessage);
                                $('.article-entry-stock-success').removeClass('d-none');
                            }
                            else {
                                $('.ref-entry-stock-success .field-success-page').html(successMessage);
                                $('.ref-entry-stock-success').removeClass('d-none');
                            }

                            $successPage.removeClass('d-none');
                            $successPage.find('.bookmark-icon').removeClass('d-none');

                            setTimeout(() => {
                                window.location.href = Routing.generate('kiosk_index', {token}, true);
                            }, 10000);
                        }
                        else {
                            console.error(msg);
                        }
                    })));
        }
        else {
            const $modalMissingRequiredFields = $(`#modal-missing-required-fields`);
            $modalMissingRequiredFields.modal(`show`);
            $modalMissingRequiredFields.find(`.bookmark-warning`).removeClass(`d-none`);
        }
    });

    $('#submitGiveUpStockEntry').on('click', function () {
        window.location.href = Routing.generate('kiosk_index', {token}, true);
    });

    $('.print-article').on('click', function () {
        wrapLoadingOnActionButton($(this), () => printLabelWithOptions(token, null, true, $(this).data('article')));
    });

    $('.print-again-button').on('click', function () {
        wrapLoadingOnActionButton($(this), () => printLabelWithOptions(token, null, true))
    });

    if( $('input[name=reference-article-input]').val()){
        $('.button-next').trigger('click');
    }
});

function printLabelWithOptions(token, barcodesToPrint, reprint = false, article = null){
    return AJAX.route(POST, 'kiosk_print', {
        barcodesToPrint,
        token,
        reprint,
        article,
    }).file({
        success: "Vos étiquettes ont bien été téléchargées",
        error: "Erreur lors de l'impression des étiquettes"
    });
}
