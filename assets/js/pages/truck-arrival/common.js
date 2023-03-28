export function initTrackingNumberSelect($trackingNumberSelect, $warningMessage ,minTrackingNumberLength ,maxTrackingNumberLength) {
    $trackingNumberSelect.off('change.lengthCheck').on('change.lengthCheck', function () {
        let $options = $(this).find('option:selected')
        let isInvalidLength = false;

        // Wait for select2 to render the options
        setTimeout(function () {
            $options.each(function () {
                let $option = $(this);
                let value = $option.val();
                if ((value.length < minTrackingNumberLength || value.length > maxTrackingNumberLength) && (maxTrackingNumberLength || minTrackingNumberLength)) {
                    $options.closest('label').find('.select2-container ul.select2-selection__rendered li.select2-selection__choice[title="' + value + '"]').addClass('warning');
                    isInvalidLength = true;
                } else {
                    $option.removeClass('invalid');
                }
            });
            if (isInvalidLength) {
                $warningMessage.removeClass('d-none');
            } else {
                $warningMessage.addClass('d-none');
            }
        }, 10);
    })
}

export function setTrackingNumberWarningMessage($warningMessage, minTrackingNumberLength, maxTrackingNumberLength) {
    if (minTrackingNumberLength) {
        if (maxTrackingNumberLength) {
            $warningMessage.find('.min-length').text(minTrackingNumberLength);
            $warningMessage.find('.max-length').text(maxTrackingNumberLength);
        } else {
            $warningMessage.text('Les numéros de tracking doivent faire minimum ' + minTrackingNumberLength + ' caractères.');
        }
    }
    if (maxTrackingNumberLength) {
        $warningMessage.text('Les numéros de tracking doivent faire maximum ' + maxTrackingNumberLength + ' caractères.');
    }
}
