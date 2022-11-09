import '@styles/pages/kiosk.scss';
import AJAX, {GET} from "@app/ajax";

let scannedReference = '';
const $referenceRefInput = $('input[name=reference-ref-input]');
const $referenceLabelInput = $('input[name=reference-label-input]');
const $modalGiveUpStockEntry = $("#modalGiveUpStockEntry");

$(function() {
    let modalPrintHistory = $("#modal-print-history");
    if (modalPrintHistory) {
        $('#openModalPrintHistory').on('click', function() {
            modalPrintHistory.modal('show');
        });
    }

    let modalInformation = $("#modal-information");
    if (modalInformation) {
        $('#information-button').on('click', function() {
            modalInformation.modal('show');
            modalInformation.find('.bookmark-icon').removeClass('d-none');
        });
    }

    let modalWaiting = $("#modal-waiting");

    let modalInStockWarning = $("#modal-in-stock-warning");

    Select2Old.user($('[name=applicant]'), '', 3);
    Select2Old.user($('[name=follower]'), '', 3);

    $(document).on('keypress', function(event) {
        if(event.originalEvent.key === 'Enter') {
            modalWaiting.modal('show');
            AJAX.route(GET, `reference_article_check_quantity`, {
                scannedReference: scannedReference,
            })
                .json()
                .then((data) => {
                    modalWaiting.modal('hide');
                    if(data.exist && data.inStock) {
                        let $errorMessage = modalInStockWarning.find('#stock-error-message');
                        $errorMessage.html($errorMessage.text().replace('@reference', `<span class="bold">${scannedReference}</span>`))
                        modalInStockWarning.modal('show');
                        modalInStockWarning.find('.bookmark-icon').removeClass('d-none');
                    }
                    else {
                        window.location.href = Routing.generate('kiosk_form', {scannedReference: scannedReference});
                    }
                    scannedReference = '';
                });
        } else {
            scannedReference += event.originalEvent.key;
        }
    });

    $referenceRefInput.on('keypress keyup', function(event) {
        if(event.originalEvent.key === 'Backspace' && event.type === 'keyup') {
            $referenceLabelInput.val($referenceLabelInput.val().slice(0,-1));
        } else if(event.originalEvent.key !== 'Enter' && event.originalEvent.key !== 'Backspace' && event.type === 'keypress'){
            $referenceLabelInput.val($referenceLabelInput.val()+event.originalEvent.key);
        }
    });

    $('.button-next').on('click', function (){
        const $current = $(this).closest('.entry-stock-container').find('.active');
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
            $current.removeClass('active').addClass('d-none');
            $($current.next()[0]).addClass('active').removeClass('d-none');
            $currentTimelineEvent.removeClass('current');
            $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');

            if($($current.next()[0]).hasClass('summary-container')){
                $(this).parent().removeClass('d-flex');
                $(this).parent().addClass('d-none');
                $('.give-up-button-container').addClass('d-none');
                $('.summary-button-container').removeClass('d-none').addClass('d-flex');

                //endroit où l'on rajoute toutes les infos dans les sections correspondantes pour le récapitulatif
                $('.reference-reference').html($('input[name=reference-ref-input]').val());

                const $articleDataInput = $('input[name=reference-article-input]');
                if($articleDataInput.val()){
                    $('.reference-article').html($articleDataInput.val());
                } else {
                    $('.field-article-title').removeClass('d-flex');
                    $('.field-article-title').addClass('d-none');
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
                $('.reference-free-field').html();
                $('.reference-commentary').html($('input[name=reference-comment]').val());
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
        $buttonNextContainer.removeClass('d-none');
        $buttonNextContainer.addClass('d-flex');

        $timelineSpans.each(function() {
            $(this).removeClass('current').addClass('future');
        });

        $timelineSpans.first().removeClass('future').addClass('current');

        $current.removeClass('active').addClass('d-none');
        $referenceContainer.addClass('active').removeClass('d-none');
    });

    $('.validate-stock-entry-button').on('click', function() {
        // TODO Valider
    });


    $('#submitGiveUpStockEntry').on('click', function() {
        window.location.href = Routing.generate('kiosk_index', true);
    })

    $('.print-article').on('click', function() {
        AJAX.route(GET, `print_article`, {
            article: $(this).data('article'),
        }).json().then((response) => {console.log(response)});
    });
});
