global.computeDescriptionFormValues = computeDescriptionFormValues
global.addReferenceToCart = addReferenceToCart;

export function computeDescriptionValues({length, width, height}) {
    const volumeCentimeters = length * width * height;
    const volumeMeters = volumeCentimeters / Math.pow(10, 6);
    return {
        volume: volumeMeters
            ? volumeMeters.toFixed(6)
            : volumeMeters,
        size: `${length}x${width}x${height}`,
    };
}

export function computeDescriptionFormValues({$length, $width, $height, $volume, $size}) {
    const {volume, size} = computeDescriptionValues({
        length: Number($length.val()),
        width: Number($width.val()),
        height: Number($height.val()),
    });

    $volume.val(volume);
    $size.val(size);
}

export function computeDescriptionShowValues() {
    const $descriptionContainer = $('.description-container');
    const {volume, size} = computeDescriptionValues({
        length: Number($descriptionContainer.find('.length').text()),
        width: Number($descriptionContainer.find('.width').text()),
        height: Number($descriptionContainer.find('.height').text()),
    });

    $descriptionContainer.find('.volume').text(volume);
    $descriptionContainer.find('.size').text(size);
}

function addReferenceToCart($element) {
    const reference = $element.data(`reference`);
    const path = Routing.generate(`cart_add_reference`, {reference});

    $.post(path, function(response) {
        if (response.success) {
            $(`.header-icon.cart`).find('.icon-figure').text(response.count)[response.count ? `removeClass` : `addClass`](`d-none`);
            showBSAlert(response.msg, `success`);
        } else {
            showBSAlert(response.msg, `info`);
        }
    });
}
