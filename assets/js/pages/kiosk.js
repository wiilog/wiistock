import '@styles/pages/kiosk.scss';
import AJAX, {GET} from "@app/ajax";

let scannedReference = '';
const $referenceRefInput = $('.reference-ref-input');
const $referenceLabelInput = $('.reference-label-input');
let $inputStockEntryData = $('input[name=stockEntryData]');

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
                        scannedReference = ''
                    }
                    else if(data.exist && !data.inStock) {

                    }
                    else {
                        window.location.href = Routing.generate('kiosk_form', {scannedReference: scannedReference});
                    }
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
        $currentTimelineEvent.removeClass('current');
        $($currentTimelineEvent.next()[0]).addClass('current').removeClass('future');
        $current.removeClass('active').addClass('d-none');
        $($current.next()[0]).addClass('active').removeClass('d-none');
    });

    $('.give-up-button').on('click', function() {
        $("#modalGiveUpStockEntry").modal('show');
        $("#modalGiveUpStockEntry").find('.bookmark-icon').removeClass('d-none');
    });

    $('#submitGiveUpStockEntry').on('click', function() {
        window.location.href = Routing.generate('kiosk_index', true);
    })
});
