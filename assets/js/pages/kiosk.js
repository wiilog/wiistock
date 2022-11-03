import '@styles/pages/kiosk.scss';

let scannedReference = '';
const $referenceRefInput = $('.reference-ref-input');
const $referenceLabelInput = $('.reference-label-input');

$(function() {
    $(document).on('keypress', function(event) {
        if(event.originalEvent.key === 'Enter') {
            window.location.href = Routing.generate('kiosk_form', {scannedReference: scannedReference});
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
