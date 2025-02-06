import {ENTRIES_TO_HANDLE_BY_TRACKING_DELAY} from "@app/pages/dashboard/render";

export function addEntryTimeInterval($button, time = null, notEmptySegment = false, fromNature = false, color = null) {
    const current = $button.data(`current`);
    let segmentUnit = getSegmentUnit($button);

    if (notEmptySegment) {
        const lastSegmentHourEndValue = $('.segment-hour').last().val();
        const lastSegmentLabel = $('.segment-container label').last().text();

        if (!lastSegmentHourEndValue && lastSegmentLabel) {
            showBSAlert('Le <strong>' + lastSegmentLabel.toLowerCase() + '</strong> doit contenir une valeur de fin', 'danger');
            return false;
        }
    }

    const $newSegmentInput = $(`
        <div class="segment-container interval">
            <div class="form-group row align-items-center">
                <label class="${fromNature ? 'col-2' : 'col-3'} wii-field-name">Segment <span class="segment-value">0</span></label>
                <div class="input-group ${fromNature ? 'col-6' : 'col-7'}">
                    <input type="text"
                           class="data needed form-control text-center display-previous segment-hour"
                           ${current === 0 ? "value=" + (fromNature ? "00h00" : `1${segmentUnit}`) : ''}
                           title="Heure de début du segment"
                           style="border: none; background-color: #e9ecef; color: #b1b1b1"
                           disabled />
                    <div class="input-group-append input-group-prepend">
                        <span class="input-group-text" style="border: none;">à</span>
                    </div>
                    <input type="text"
                           class="data-array form-control ${!fromNature ? 'needed' : ''} text-center segment-hour"
                           name="segments"
                           data-no-stringify
                           ${fromNature ? "pattern=\"^(\\d{1,3})h([0-5]\\d)$\"\n data-error-patternmismatch=\"Format de date incorrect\"" : ''}
                           title="Heure de fin du segment"
                           style="border: none; background-color: #e9ecef;"
                           ${time !== null ? 'value="' + time + '"' : ''}
                           onkeyup="onSegmentInputChange($(this), false, ${fromNature})"
                           onfocusout="onSegmentInputChange($(this), true, ${fromNature})" />
                </div>
                ${fromNature
        ? `<div class="col-2">
                        ${getInputColor('segmentColor', color ?? false , 'data-array')}
                        </div>`
        : ''
    }
                <div class="col-2">
                    <button class="btn d-block" onclick="deleteEntryTimeInterval($(this), ${fromNature})"><span class="wii-icon wii-icon-trash-black mr-2"></span></button>
                </div>
            </div>
        </div>
    `);

    const $lastSegmentValues = $button.closest('.modal').find('.segment-value');
    const $currentSegmentValue = $newSegmentInput.find('.segment-value');
    const $lastSegmentValue = $lastSegmentValues.last();
    const lastSegmentValue = parseInt($lastSegmentValue.text() || '0');
    $currentSegmentValue.text(lastSegmentValue + 1);

    $newSegmentInput.insertBefore($button);
    recalculateIntervals();
}

export function deleteEntryTimeInterval($button, fromNature = false) {
    const $segmentContainer = $('.segment-container');

    if ($segmentContainer.length === 1 && !fromNature) {
        showBSAlert('Au moins un segment doit être renseigné', 'danger');
        event.preventDefault();
        return false;
    }

    const $currentSegmentContainer = $button.closest('.segment-container');
    const $nextsegmentContainers = $currentSegmentContainer.nextAll().not('button');

    $nextsegmentContainers.each(function () {
        const $currentSegment = $(this);
        const $segmentValue = $currentSegment.find('.segment-value');
        $segmentValue.text(parseInt($segmentValue.text()) - 1);
    });
    $currentSegmentContainer.remove();
    $button.data(`current`, 0);
    recalculateIntervals();
}

function recalculateIntervals() {
    let previous = null;

    $(`.segments-list > .interval`).each(function () {
        if (previous) {
            $(this).find(`.display-previous`).val(previous);
        }

        previous = $(this).find(`input[name="segments"]`).val();
    });
}

export function initializeEntryTimeIntervals($modal, fromNature = false) {
    const $button = $modal.find(`.add-time-interval`);
    const $segmentContainer = $modal.find(`.segment-container`);

    if($segmentContainer.length > 0){
        $segmentContainer.empty();
    }

    $button.data(`current`, 0);
    const $segmentsList = $modal.find('.segments-list');
    if ($segmentsList.length > 0) {
        const segments = $segmentsList.data(`segments`);
        const colors = $segmentsList.data(`colors`);
        if (segments.length > 0) {
            for (let [index, segment] of Object.entries(segments)) {
                addEntryTimeInterval($button, segment, false, fromNature, colors[index]);
            }
        } else if (!fromNature){
            addEntryTimeInterval($button, null, false, true);
        }
    }
}

export function onSegmentInputChange($input, isChanged = false, fromNature = false) {
    let segmentUnit = getSegmentUnit($input);
    if(!fromNature) {
        const value = $input.val();
        const smartValue = clearSegmentHourValues(value);
        const newVal = smartValue && (parseInt(smartValue) + (isChanged ? segmentUnit : ''));

        $input.val(newVal);
    }

    if (isChanged) {
        recalculateIntervals();
    }
}

export function clearSegmentHourValues(value) {
    const cleared = (value || ``).replace(/[^\d]/g, ``);
    return cleared ? parseInt(cleared) : ``;
}

function getInputColor(name, value = false, inputClass = '') {
    return `
        <input type='color' class='${inputClass} form-control wii-color-picker data' name='${name}' value='${value ?? '#3353D7'}' list='type-color'/>
        <datalist>
            <option>#D76433</option>
            <option>#D7B633</option>
            <option>#A5D733</option>
            <option>#33D7D1</option>
            <option>#33A5D7</option>
            <option>#3353D7</option>
            <option>#6433D7</option>
            <option>#D73353</option>
        </datalist>
    `
}
function getSegmentUnit($formElement) {
    const meterKey = $formElement.closest('.modal').data('meter-key');
    let segmentUnit;
    switch(meterKey) {
        case ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
            segmentUnit = "min";
            break;
        default:
            segmentUnit = "h";
            break;
    }

    return segmentUnit;
}
