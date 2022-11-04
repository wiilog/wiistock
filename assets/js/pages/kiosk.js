import '@styles/pages/kiosk.scss';
import AJAX, {GET} from "@app/ajax";

let scannedReference = '';
const $referenceRefInput = $('.reference-ref-input');
const $referenceLabelInput = $('.reference-label-input');

$(function() {
    let modalPrintHistory = $("#modal-print-history");
    let modalPrintHistoryCloseButton = modalPrintHistory.find("#cancel");
    InitModal(modalPrintHistory, modalPrintHistoryCloseButton,'',{});
    $('#openModalPrintHistory').on('click', function() {
        modalPrintHistory.modal('show');
    });

    let modalInStockWarning = $("#modal-in-stock-warning");
    let modalInStockWarningCloseButton = modalInStockWarning.find("#cancel");
    InitModal(modalPrintHistory, modalPrintHistoryCloseButton,'',{});

    $(document).on('keypress', function(event) {
        if(event.originalEvent.key === 'Enter') {
            AJAX.route(GET, `reference_article_check_quantity`, {
                scannedReference: scannedReference,
            })
                .json()
                .then((data) => {
                    if(data.exist && data.inStock) {
                        let $errorMessage = modalInStockWarning.find('#stock-error-message');
                        $errorMessage.html($errorMessage.text().replace('@reference', `<span class="bold">${scannedReference}</span>`))
                        modalInStockWarning.modal('show');
                        modalInStockWarning.find('.bookmark-icon').removeClass('d-none');
                    }
                    else {
                        window.location.href = Routing.generate('kiosk_form', {scannedReference: scannedReference});
                    }
                    scannedReference = ''
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
        console.log($('.entry-stock-container').children());
    });
});
