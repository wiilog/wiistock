import '@styles/pages/kiosk.scss';
import AJAX, {GET, POST} from "@app/ajax";
import Flash, {SUCCESS, ERROR} from "@app/flash";

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

$(function() {
    if ($modalPrintHistory) {
        $('#openModalPrintHistory').on('click', function() {
            $modalPrintHistory.modal('show');
        });
    }

    if ($modalInformation) {
        $('#information-button').on('click', function() {
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
                AJAX.route(GET, `reference_article_check_quantity`, {scannedReference})
                    .json()
                    .then(({exists, inStock}) => {
                        $modalWaiting.modal('hide');
                        if (exists && inStock) {
                            let $errorMessage = $modalInStockWarning.find('#stock-error-message');
                            $errorMessage.html(originalMessage.replace('@reference', `<span class="bold">${scannedReference}</span>`));
                            $modalInStockWarning.modal('show');
                            $modalInStockWarning.find('.bookmark-icon').removeClass('d-none');
                        } else {
                            window.location.href = Routing.generate('kiosk_form', {scannedReference: scannedReference});
                        }
                        scannedReference = '';
                    });
            } else {
                scannedReference += event.originalEvent.key;
            }
        }
    });

    $referenceRefInput.on('keypress keyup search', function(event) {
        $referenceLabelInput.val($referenceRefInput.val());
    });

    $('.button-next').on('click', function (){
        const $current = $(this).closest('.entry-stock-container').find('.active');
        const $buttonNextContainer = $(this).parent();
        const $timeline = $('.timeline-container');
        const $currentTimelineEvent = $timeline.find('.current');
        const $inputs = $current.find('input[required]');
        const $selects = $current.find('select.needed');

        $selects.each(function(){
            if($(this).find('option:selected').length === 0){
                $(this).parent().find('.select2-selection ').addClass('invalid');
            } else {
                $(this).parent().find('.select2-selection ').removeClass('invalid');
            }
        });

        $inputs.each(function(){
            if (!($(this).val())){
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });

        if($current.find('.invalid').length === 0){
            if($($current.next()[0]).hasClass('summary-container')){
                const $articleDataInput = $('input[name=reference-article-input]');
                $.post(Routing.generate('check_article_is_valid'), {articleLabel: $articleDataInput.val()}, function(response){
                    if(response.success || !response.fromArticlePage){
                        $current.removeClass('active').addClass('d-none');
                        $($current.next()[0]).addClass('active').removeClass('d-none');
                        $currentTimelineEvent.removeClass('current');
                        $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');

                        $buttonNextContainer.removeClass('d-flex').addClass('d-none');
                        $('.give-up-button-container').removeClass('d-flex').addClass('d-none');
                        $('.summary-button-container').removeClass('d-none').addClass('d-flex');

                        //endroit où l'on rajoute toutes les infos dans les sections correspondantes pour le récapitulatif
                        $('.reference-reference').html($('input[name=reference-ref-input]').val());
                        if($articleDataInput.val()){
                            $('.reference-article').html($articleDataInput.val());
                        } else {
                            $('.field-article-title').removeClass('d-flex').addClass('d-none');
                        }

                        $('.reference-label').html($('input[name=reference-label-input]').val());

                        const $applicantInput = $('select[name=applicant] option:selected');
                        const $followerInput = $('select[name=follower] option:selected');
                        if($applicantInput.val()){
                            $('.reference-managers').html(
                                $followerInput.val() ?
                                    $applicantInput.text().concat(', ', $followerInput.text()) :
                                    $applicantInput.text());
                        }
                        let $freeFieldLabel = $('.free-field-label');
                        $('.reference-free-field').html(
                            $freeFieldLabel.find('input').val()
                            || $freeFieldLabel.find('textarea').val()
                            || $freeFieldLabel.find('select').find('option:selected').text());
                        $('.reference-commentary').html($('input[name=reference-comment]').val());
                    } else {
                        $modalArticleIsNotValid.modal('show');
                        $modalArticleIsNotValid.find('.bookmark-icon').removeClass('d-none');
                    }
                });
            } else {
                $current.removeClass('active').addClass('d-none');
                $($current.next()[0]).addClass('active').removeClass('d-none');
                $currentTimelineEvent.removeClass('current');
                $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');
            }
        }
    });

    $('.return-or-give-up-button').on('click', function() {
        const $current = $('.active')
        const $timeline = $('.timeline-container');
        const $currentTimelineEvent = $timeline.find('.current');

        if(!$current.hasClass('.reference-container') && $current.prev()[0]) {
            $currentTimelineEvent.addClass('future').removeClass('current');
            $($currentTimelineEvent.prev()[0]).addClass('current').removeClass('future');
            $current.removeClass('active').addClass('d-none');
            $($current.prev()[0]).addClass('active').removeClass('d-none');
        } else {
            $modalGiveUpStockEntry.modal('show');
            $modalGiveUpStockEntry.find('.bookmark-icon').removeClass('d-none');
        }
    });

    $('.give-up-button').on('click', function() {
        $modalGiveUpStockEntry.modal('show');
        $modalGiveUpStockEntry.find('.bookmark-icon').removeClass('d-none');
    });

    $('.edit-stock-entry-button').on('click', function() {
        const $current = $(this).closest('.entry-stock-container').find('.active');
        const $referenceContainer = $(this).closest('.entry-stock-container').find('.reference-container');
        const $buttonNextContainer = $('.button-next').parent();
        const $timelineSpans = $('.timeline-container span');
        $('.give-up-button-container').removeClass('d-none').addClass('d-flex');
        $('.summary-button-container').removeClass('d-flex').addClass('d-none');
        $buttonNextContainer.removeClass('d-none').addClass('d-flex');

        $timelineSpans.each(function() {
            $(this).removeClass('current').addClass('future');
        });

        $timelineSpans.first().removeClass('future').addClass('current');

        $current.removeClass('active').addClass('d-none');
        $referenceContainer.addClass('active').removeClass('d-none');
    });

    $('.validate-stock-entry-button').on('click', function() {
        $('#modal-waiting').modal('show');
        let $freeFieldLabel = $('.free-field-label');
        let freeFieldValue = $freeFieldLabel.find('input').val()
            || $freeFieldLabel.find('textarea').val()
            || $freeFieldLabel.find('select').find('option:selected').data('label');
        let freeFieldId =  $('input[name=free-field-id]').val();
        AJAX.route(GET, 'entry_stock_validate', {
            'reference': $('input[name=reference-ref-input]').val(),
            'label': $('input[name=reference-label-input]').val(),
            'article': $('input[name=reference-article-input]').val() || null,
            'applicant': $('select[name=applicant] option:selected').val(),
            'follower': $('select[name=follower] option:selected').val(),
            'comment': $('input[name=reference-comment]').val(),
            'freeField': freeFieldId && freeFieldValue ? [freeFieldId, freeFieldValue] : [],
        }).json().then((res) => {
            if(res.success){
            const $successPage =  $('.success-page-container');
            $('.main-page-container').addClass('d-none');

            $('.go-home-button').on('click', function() {
                window.location.href = Routing.generate('kiosk_index', true);
            })

            if(res.referenceExist){
                $('.print-again-button').addClass('d-none');
                $('.article-entry-stock-success .field-success-page').html(res.successMessage);
                $('.article-entry-stock-success').removeClass('d-none');
            } else {
                $('.ref-entry-stock-success .field-success-page').html(res.successMessage);
                $('.ref-entry-stock-success').removeClass('d-none');
            }

            $successPage.removeClass('d-none');
            $successPage.find('.bookmark-icon').removeClass('d-none');
            $('#modal-waiting').modal('hide');
            setTimeout(() => {
                window.location.href = Routing.generate('kiosk_index', true);
            }, 10000);
        }});
    });

    $('#submitGiveUpStockEntry').on('click', function() {
        window.location.href = Routing.generate('kiosk_index', true);
    });

    $('.print-article').on('click', function() {
        AJAX.route(GET, `print_article`, {
            article: $(this).data('article'),
        }).json().then((response) => {});
    });

    $('.print-again-button').on('click', function() {
        AJAX.route(GET, `print_article`, {
            reprint : true
        }).json().then((response) => {});
    });
});
