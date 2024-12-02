import moment from 'moment';
import Flash from '@app/flash';
import AJAX, {GET} from '@app/ajax';

export const MAX_UPLOAD_FILE_SIZE = 10000000;
export const MAX_IMAGE_PIXELS = 1000000;
export const ALLOWED_IMAGE_EXTENSIONS = [`png`, `jpeg`, `jpg`, `svg`, `gif`];

global.MAX_UPLOAD_FILE_SIZE = MAX_UPLOAD_FILE_SIZE;
global.MAX_IMAGE_PIXELS = MAX_IMAGE_PIXELS;
global.ALLOWED_IMAGE_EXTENSIONS = ALLOWED_IMAGE_EXTENSIONS;

global.updateImagePreview = updateImagePreview;
global.resetImage = resetImage;
global.onSettingsItemSelected = onSettingsItemSelected;
global.exportFile = exportFile;
global.getUserFiltersByPage = getUserFiltersByPage;
global.clearFilters = clearFilters;
global.saveFilters = saveFilters;
global.updateRequiredMark = updateRequiredMark;

function updateImagePreview(preview, upload, $title = null, $delete = null, $callback = null) {
    let $upload = $(upload)[0];
    let formats;

    if($(upload).is('[accept]')) {
        const inputAcceptedFormats = $(upload).attr('accept').split(',');
        formats = inputAcceptedFormats.map((format) => {
            format = format.split("/").pop();
            return format.indexOf('+') > -1 ? format.substring(0, format.indexOf('+')) : format;
        });
    } else {
        formats = ALLOWED_IMAGE_EXTENSIONS;
    }

    if ($upload.files && $upload.files[0]) {
        let fileNameWithExtension = $upload.files[0].name.split('.');
        let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

        if ($upload.files[0].size < MAX_UPLOAD_FILE_SIZE) {
            if (formats.indexOf(extension.toLowerCase()) !== -1) {
                if ($title) {
                    $title.text(fileNameWithExtension.join('.').substr(0, 5) + '...');
                    $title.attr('title', fileNameWithExtension.join('.'));
                    if($title.siblings('input[name=titleComponentLogo]').length > 0) {
                        $title.siblings('input[name=titleComponentLogo]').last().val($upload.files[0].name);
                    }
                }

                let reader = new FileReader();
                reader.onload = function (e) {
                    let image = new Image();

                    image.onload = function() {
                        const pixels = image.height * image.width;
                        if (pixels <= MAX_IMAGE_PIXELS) {
                            if ($callback) {
                                $callback($upload);
                            }
                            $(preview)
                                .attr('src', e.target.result)
                                .removeClass('d-none');
                            if ($delete) {
                                $delete.removeClass('d-none');
                            }
                        } else {
                            showBSAlert('Veuillez choisir une image ne faisant pas plus de 1000x1000', 'danger');
                        }
                    };

                    image.src = e.target.result;
                };

                reader.readAsDataURL($upload.files[0]);
            } else {
                showBSAlert(`Veuillez choisir un format d'image valide (${formats.join(`, `)})`, 'danger')
            }
        } else {
            showBSAlert('La taille du fichier doit être inférieure à 10Mo', 'danger')
        }
    }
}

export function showAndRequireInputByType($typeSelect) {
    const $modal = $typeSelect.closest('.modal');
    // find all fixed fields that can be configurable
    const $fields = $modal.find('[data-displayed-type]');

    // remove required symbol from all fields
    $fields.find('.required-symbol')
        .remove();
    $fields.find('.data')
        .removeClass('needed')
        .prop('required', false)

    // find all fields that should be displayed
    const $fieldsToDisplay = $fields.filter(`[data-displayed-type~="${$typeSelect.val()}"]`);

    // find all fields that should be required
    const $fieldsRequired = $fieldsToDisplay.filter(`[data-required-type~="${$typeSelect.val()}"]`);

    // add required symbol to all required fields
    $fieldsRequired.find('.field-label')
        .append($('<span class="required-symbol">*</span>'));
    $fieldsRequired.find('.data')
        .addClass('needed')
        .prop('required', true)

    // find all fields that should not be required and remove required attribute
    const $fieldsNotRequired = $fieldsToDisplay.not($fieldsRequired);
    $fieldsNotRequired.find('.data')
        .removeClass('is-invalid');
    $fieldsNotRequired.find('.invalid-feedback')
        .remove();
    $fieldsNotRequired.find('.invalid-feedback')
        .remove();

    // hide the fields that should not be displayed
    $fields.not($fieldsToDisplay)
        .addClass('d-none');
    // show the fields that should be displayed
    $fieldsToDisplay.removeClass('d-none');
}


function resetImage($button) {
    const $defaultValue = $button.siblings('.default-value');
    const $image = $button.siblings('.preview-container').find('.image');
    const $keepImage = $button.siblings('.keep-image');
    const $input = $button.siblings('[type="file"]');
    const defaultValue = $defaultValue.val();

    $input.val('');
    $image
        .attr('src', defaultValue)
        .removeClass('d-none');
    $keepImage.val(0);

    if (defaultValue === '') {
        $image.addClass('d-none');
    }
}

function onSettingsItemSelected($selected, $settingsItems, $settingsContents, options = {}) {
    const $buttons = $('main .save');
    const selectedKey = $selected.data('menu');

    $settingsItems.removeClass('selected');
    $settingsContents.addClass('d-none');

    $selected.addClass('selected');
    const $menu = $settingsContents.filter(`[data-menu="${selectedKey}"]`);
    $menu.removeClass('d-none');

    const $firstMenu = $menu.find('.menu').first();
    if ($firstMenu.length) {
        onSettingsItemSelected($firstMenu, $('.menu'), $('.menu-content'));
    }
    if (options.hideClass && options.hiddenElement){
        if ($selected.hasClass(options.hideClass)) {
            options.hiddenElement.addClass('d-none');
        }
        else {
            options.hiddenElement.removeClass('d-none');
        }
    }

    $buttons.removeClass('d-none');
}

/**
 * @param {string} route route name for export
 * @param {{[string]: any}} params query params for export
 * @param {{
 *      needsDates: boolean|undefined,
 *      needsAllFilters: boolean|undefined,
 *      needsDateFormatting: boolean|undefined
 *      $button: jQuery,
 * }} options Object containing some options.
 *   - determine if date filters are required
 *   - determine if all filters are added into query
 *   - determine if user date format needs to be used
 *   - jQuery object for loading
 */
function exportFile(route, params = {}, options = {}) {
    const needsDates = options.needsDates || true;
    const needsAllFilters = options.needsAllFilters || false;
    const needsDateFormatting = options.needsDateFormatting || false;
    const $button = options.$button || $(`.fa-file-csv`).closest(`button`);

    const $filtersContainer = $(`.filters-container`);
    let dateMin = $filtersContainer.find(`[name=dateMin]`).val();
    let dateMax = $filtersContainer.find(`[name=dateMax]`).val();
    if(needsDates && (!dateMin || !dateMax)) {
        Flash.add(Flash.ERROR, Translation.of(`Général`, null, `Modale`, `Veuillez saisir des dates dans le filtre en haut de page.`));
        return;
    }

    if(needsAllFilters) {
        params = {
            ...params,
            ...serializeFilters(),
        }
    }

    const dateFormat = needsDateFormatting ? $(`#userDateFormat`).val() : `d/m/Y`;
    dateMin = moment(dateMin, DATE_FORMATS_TO_DISPLAY[dateFormat]).format(`YYYY-MM-DD`);
    dateMax = moment(dateMax, DATE_FORMATS_TO_DISPLAY[dateFormat]).format(`YYYY-MM-DD`);

    params = {
        ...params,
        dateMin,
        dateMax,
    }

    return wrapLoadingOnActionButton($button, () => (
        AJAX
            .route(AJAX.GET, route, params)
            .file({})
            .then(() => showBSAlert(`Le fichier a bien été téléchargé.`, `success`))
            .catch(() => showBSAlert(`Une erreur est survenue lors de l'export des données. Veuillez réduire le volume de données exportées.`, `danger`))
    ));
}

export function dataURLtoFile(dataURL, filename) {
    const arr = dataURL.split(`,`);
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[arr.length - 1]);
    let index = bstr.length;
    const u8arr = new Uint8Array(index);

    while (index--) {
        u8arr[index] = bstr.charCodeAt(index);
    }

    return new File([u8arr], filename, {type: mime});
}

export function getUserFiltersByPage(page,
                                     options = {preventPrefillFilters: false},
                                     callback = undefined) {
    AJAX.route(GET, `filter_get_by_page`, {page})
        .json()
        .then((data) => {
            if (!options.preventPrefillFilters) {
                displayFiltersSup(data);
            }

            if (callback) {
                callback();
            }
        });
}

/**
    * Allow to clear all filters and save the new preferences
    * All params is usefully to saveFilters function
    * @param {string} page
    * @param {string} tableSelector
    * @param {function} callback
    * @param {boolean} needsDateFormatting
    * @return {void}
    * @example
    * clearFilters('page', '#table', () => {
    *    console.log('filters cleared');
    * }
 **/
function clearFilters (
    page,
    tableSelector,
    callback,
    needsDateFormatting = false
) {
    // function to clear datepicker
    const clearDatePicker = ($element) => {
        const $picker = $element.data("DateTimePicker");
        if ($picker) {
            $picker.clear();
        }
    };

    // clear datepicker
    clearDatePicker($('.filter-date-min'));
    clearDatePicker($('.filter-date-max'));
    clearDatePicker($('.filter-date-expected'));

    // clear inputs
    document.querySelectorAll('.filters .form-control').forEach(filter => {
        filter.value = '';
    });

    // clear checkboxes
    document.querySelectorAll('.filters .filter-checkbox').forEach(filter => {
        const dateChoiseFilter = $(filter).closest('.date-choice');
        if(dateChoiseFilter.length === 0){
            filter.checked = false;
        }
    });

    // clear select2
    document.querySelectorAll('.filters .select2, .filters .filter-select2').forEach(filter => {
        $(filter).val(null).trigger('change');
    });

    // reload datatable & reset filters preferences
    saveFilters(page, tableSelector, callback, needsDateFormatting);
};

/**
 * Allow to save all filters and save the new preferences
 * @param page
 * @param tableSelector
 * @param callback
 * @param needsDateFormatting
 * @returns {JQuery.jqXHR}
 */
function saveFilters(page, tableSelector, callback, needsDateFormatting = false) {
    const $table = $(tableSelector);
    const path = Routing.generate('filter_sup_new');

    const $filterDateMin = $('.filter-date-min:visible');
    const $filterDateMax = $('.filter-date-max:visible');
    const $filterDateExpected = $('.filter-date-expected:visible');
    const $filterDateMinPicker = $filterDateMin.data("DateTimePicker");
    const $filterDateMaxPicker = $filterDateMax.data("DateTimePicker");
    const $filterDateExpectedPicker = $filterDateExpected.data("DateTimePicker");

    if ($filterDateMinPicker) {
        $filterDateMinPicker.format('YYYY-MM-DD');
    }
    if ($filterDateMaxPicker) {
        $filterDateMaxPicker.format('YYYY-MM-DD');
    }
    if ($filterDateExpectedPicker) {
        $filterDateExpectedPicker.format('YYYY-MM-DD');
    }

    let params = {
        page,
        ...serializeFilters($table),
    }

    let format = 'd/m/Y';
    if (needsDateFormatting) {
        const $userFormat = $('#userDateFormat');
        format = $userFormat.val() ? $userFormat.val() : 'd/m/Y';
    }
    if ($filterDateMinPicker) {
        $filterDateMinPicker.format(DATE_FORMATS_TO_DISPLAY[format]);
    }
    if ($filterDateMaxPicker) {
        $filterDateMaxPicker.format(DATE_FORMATS_TO_DISPLAY[format]);
    }
    if ($filterDateExpectedPicker) {
        $filterDateExpectedPicker.format(DATE_FORMATS_TO_DISPLAY[format]);
    }

    return $.post(path, JSON.stringify(params), function (response) {
        if (response) {
            if (callback) {
                callback();
            }
            if (tableSelector) {
                $(tableSelector).each(function() {
                    const $table = $(this);
                    if ($table && $table.DataTable && $table.is(`:visible`)) {
                        $table.DataTable().draw();
                    }
                })
            }
        } else {
            showBSAlert('Veuillez saisir des filtres corrects (pas de virgule ni de deux-points).', 'danger');
        }
    }, 'json');
}

/**
 * Manage the given button which is active when the datatable is filled
 * @param {DataTable.Api} datatable Datatable to test
 * @param {jQuery} $printButton
 * @param {function} filledFilters Return boolean, true if search input is filled
 */
export function togglePrintButton(datatable, $printButton, filledFilters) {
    const datatableLength = datatable.rows().count();
    const disablePrintButton = (!filledFilters() || datatableLength === 0);

    // for an active print button
    $printButton.toggleClass(`pointer`, !disablePrintButton);

    // for a disabled print button
    $printButton
        .toggleClass(`user-select-none`, disablePrintButton)
        .toggleClass(`disabled`, disablePrintButton)
        .toggleClass(`has-tooltip`, disablePrintButton)
        .tooltip(disablePrintButton ? undefined : 'dispose');
}

export function generateRandomNumber() {
    return Math.floor(Math.random() * 1000000);
}

export function updateRequiredMark($element, isRequired) {
    const $label = $element.find('.required-mark');

    if (!!isRequired) {
        if (!$label.length) {
            $element.append($('<span class="required-mark">*</span>'));
        }
    } else {
        $label.remove();
    }
}
