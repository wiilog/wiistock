
export function computeDescriptionValues({length, width, height}) {
    const volume = length * width * height;
    return {
        volume: volume ? volume.toFixed(3) : volume,
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
