const FORM_INVALID_CLASS = 'is-invalid';
const FORM_ERROR_CONTAINER = 'error-msg';
const FILE_MAX_SIZE = 10000000;

let droppedFiles = [];

/**
 * Init form validation modal.
 *
 * @param {jQuery} $modal jQuery element of the modal
 * @param {jQuery|string} submit jQuery element of the submit button
 * @param {string} path url to call on submit
 * @param {undefined | function(): Promise<boolean>} waitForUserAction function run on submit button click and we wait for true return
 * @param {{
 *      confirmMessage: function|undefined,
 *      tables: undefined|Array<jQuery>,
 *      keepModal: undefined|boolean,
 *      keepForm: undefined|boolean,
 *      success: undefined|function,
 *      clearOnClose: undefined|boolean,
 *      validator: undefined|function,
 *      waitDatatable: undefined|boolean,
 *      keepLoading: undefined|boolean
 * }} options Object containing some option.
 *   - tables is an array of datatable
 *   - keepForm is an array of datatable
 *   - keepModal true if we do not close form
 *   - success handler for success
 *   - clearOnClose clear the modal on close action
 *   - validator function which calculate custom form validation
 *   - confirmMessage Function which return promise throwing when form can be submitted
 *   - waitDatatable if true returned a Promise resolve whe Datatable is reloaded
 *   - keepLoading Keep loader on submit button after receiving ajax response
 */
function InitModal($modal, submit, path, options = {}) {
    if(options.clearOnClose) {
        $modal.on('hidden.bs.modal', function () {
            clearModal($modal);
            clearFormErrors($modal);
        });
    }

    $modal.on('show.bs.modal', function () {
        $('[data-toggle="popover"]').popover("hide");
    });

    const onclick = function () {
        const $button = $(this);
        if ($button.hasClass(LOADING_CLASS)) {
            showBSAlert(Translation.of('Général', '', 'Modale', 'L\'opération est en cours de traitement'), 'info');
        } else {
            SubmitAction($modal, $button, path, options)
                .catch((err) => {
                    // do not remove for debug
                    console.error(err);
                });
        }
    };

    //if it's a string, find the button in the modal
    if(typeof submit === 'string') {
        $modal.on(`click`, submit, onclick);
    } else {
        submit.on(`click`, onclick);
    }
}

/**
 *
 * @param {{
 *      confirmMessage: function|undefined,
 *      tables: undefined|Array<jQuery>,
 *      keepModal: undefined|boolean,
 *      success: function,
 *      keepForm: undefined|boolean,
 *      validator: function|undefined,
 *      waitDatatable: undefined|boolean,
 *      keepLoading: undefined|boolean
 * }} options Object containing some options.
 *   - tables is an array of datatable
 *   - keepForm true if we do not clear form
 *   - keepModal true if we do not close form
 *   - validator function which calculate custom form validation
 *   - confirmMessage Function which return promise throwing when form can be submitted
 *   - success called on success
 *   - waitDatatable if true returned a Promise resolve whe Datatable is reloaded,
 *   - keepLoading Keep loader on submit button after receiving ajax response
 * @param {jQuery} $modal jQuery element of the modal
 * @param {jQuery} $submit jQuery element of the submit button
 * @param {string} path
 */
function SubmitAction($modal,
                      $submit,
                      path,
                      {confirmMessage, ...options} = {}) {
    clearFormErrors($modal);

    return (
        confirmMessage
            ? confirmMessage($modal)
            : (new Promise((resolve) => resolve(true)))
    )
        .then((success) => {
            if(success) {
                return processSubmitAction($modal, $submit, path, options);
            }
        });
}

/**
 * @param {undefined|Array<jQuery>} tables tables is an array of datatable
 * @param {undefined|boolean} waitDatatable if true returned a Promise resolve whe Datatable is reloaded
 * @param {undefined|boolean} keepModal true if we do not close form
 * @param {undefined|boolean} keepForm true if we do not clear form
 * @param {function} success called on success
 * @param {function} error called on error
 * @param {function} waitForUserAction wait for user modal action
 * @param {function} headerCallback header callback
 * @param {function|undefined} keepLoading Keep loader on submit button after receiving ajax response
 * @param {function|undefined} validator function which calculate custom form validation
 * @param {jQuery} $modal jQuery element of the modal
 * @param {jQuery} $submit jQuery element of the submit button
 * @param {string} path
 */
function processSubmitAction($modal,
                             $submit,
                             path,
                             {tables, keepModal, keepForm, validator, success, error, headerCallback, keepLoading, waitDatatable, waitForUserAction} = {}) {
    const isAttachmentForm = $modal.find('input[name="isAttachmentForm"]').val() === '1';
    const {success: formValidation, errorMessages, $isInvalidElements, data} = ProcessForm($modal, isAttachmentForm, validator);

    if (formValidation) {
        const smartData = isAttachmentForm
            ? createFormData(data)
            : JSON.stringify(data);

        $submit.pushLoader('white');
        if (waitForUserAction) {
            return waitForUserAction()
                .then((doSubmit) => {
                    if (doSubmit) {
                        return postForm(path, smartData, $submit, $modal, data, tables, keepModal, keepForm, headerCallback, waitDatatable, success, error, keepLoading);
                    } else {
                        $submit.popLoader();
                    }
                })
                .catch(() => {});
        } else {
            return postForm(path, smartData, $submit, $modal, data, tables, keepModal, keepForm, headerCallback, waitDatatable, success, error, keepLoading);
        }
    }
    else {
        displayFormErrors($modal, {
            $isInvalidElements,
            errorMessages,
            keepModal
        });

        return new Promise((_, reject) => {
            reject(false);
        });
    }
}

function postForm(path, smartData, $submit, $modal, data, tables, keepModal, keepForm, headerCallback, waitDatatable, success, error, keepLoading) {
    return $
        .ajax({
            url: path,
            data: smartData,
            type: 'post',
            contentType: false,
            processData: false,
            cache: false,
            dataType: 'json',
        })
        .then((data) => {
            if (!keepLoading) {
                $submit.popLoader();
            }

            if (data.success === false) {
                const errorMessage = data.msg || data.message;
                displayFormErrors($modal, {
                    $isInvalidElements: data.invalidFieldsSelector ? [$(data.invalidFieldsSelector)] : undefined,
                    errorMessages: errorMessage ? [errorMessage] : undefined,
                    keepModal
                });
                if (error) {
                    error(data);
                }
            }
            else {
                const res = treatSubmitActionSuccess($modal, data, tables, keepModal, keepForm, headerCallback, waitDatatable);
                if (!res) {
                    return;
                }
                else {
                    return res
                        .then(() => {
                            if(data && data.success && success) {
                                success(data);
                            }
                        })
                }
            }

            return data;
        })
        .catch((err) => {
            $submit.popLoader();
            throw err;
        });
}

/**
 * Remove all form errors
 * @param $modal jQuery modal
 */
function clearFormErrors($modal) {
    $modal
        .find(`.${FORM_INVALID_CLASS}`)
        .removeClass(FORM_INVALID_CLASS);

    $modal
        .find(`.${FORM_ERROR_CONTAINER}`)
        .removeClass("p-4")
        .empty();
}

function treatSubmitActionSuccess($modal, data, tables, keepModal, keepForm, headerCallback, waitDatatable) {
    resetDroppedFiles();
    if (data.redirect && !keepModal) {
        window.location.href = data.redirect;
        return;
    }

    if (data.nextModal) {
        $modal.find('.modal-body').html(data.nextModal);
    }

    let tablesReloadingPromises;
    if (tables && tables.length > 0) {
        tablesReloadingPromises = tables.map((table) => {
            return new Promise((resolve) => {
                table.ajax.reload(
                    () => { resolve(); },
                    false
                );
            });
        });
    }
    else {
        tablesReloadingPromises = [new Promise((resolve) => resolve())];
    }

    if (!data.nextModal && !keepModal) {
        $modal.off('hidden.bs.modal');
        $modal.on('hidden.bs.modal', function () {
            refreshHeader(data.entete, headerCallback);
        })
        $modal.modal('hide');
    } else {
        refreshHeader(data.entete, headerCallback);
    }

    if (!keepForm) {
        clearModal($modal);
    }

    if (data.msg) {
        showBSAlert(data.msg, 'success');
    }

    if (waitDatatable) {
        return Promise.all(tablesReloadingPromises);
    } else {
        Promise.all(tablesReloadingPromises); // we launch datatable reloading even if we do not wait
        return new Promise((resolve) => {
            resolve();
        });
    }
}

function refreshHeader(entete, headerCallback) {
    if (entete) {
        $('.zone-entete').html(entete);
        $('.zone-entete [data-toggle="popover"]').popover();
        if (headerCallback) {
            headerCallback();
        }
    }
}

/**
 *
 * @param {jQuery} $modal jQuery modal
 * @param {boolean} [isAttachmentForm]
 * @param {function} [validator]
 * @return {{errorMessages: Array<string>, success: boolean, data: FormData|Object.<*,*>, $isInvalidElements: Array<*>}}
 */
function ProcessForm($modal, isAttachmentForm = undefined, validator = undefined) {
    const data = {};

    const dataArrayForm = processDataArrayForm($modal, data);
    const dataInputsForm = processInputsForm($modal, data, isAttachmentForm);
    const dataCheckboxesForm = processCheckboxesForm($modal, data, isAttachmentForm);
    const dataRadioButtonsForm = processRadioButtonsForm($modal, data, isAttachmentForm);
    const dataSwitchesForm = processSwitchesForm($modal, data, isAttachmentForm);
    const dataFilesForm = processFilesForm($modal, data);
    const dataValidator = validator
        ? (validator($modal) || {success: true, errorMessages: [], $isInvalidElements: []})
        : {success: true, errorMessages: [], $isInvalidElements: []};

    return {
        success: (
            dataArrayForm.success
            && dataInputsForm.success
            && dataCheckboxesForm.success
            && dataRadioButtonsForm.success
            && dataSwitchesForm.success
            && dataFilesForm.success
            && dataValidator.success
        ),
        errorMessages: [
            ...dataArrayForm.errorMessages,
            ...dataInputsForm.errorMessages,
            ...dataCheckboxesForm.errorMessages,
            ...dataRadioButtonsForm.errorMessages,
            ...dataFilesForm.errorMessages,
            ...dataSwitchesForm.errorMessages,
            ...(dataValidator.errorMessages || [])
        ],
        $isInvalidElements: [
            ...dataArrayForm.$isInvalidElements,
            ...dataInputsForm.$isInvalidElements,
            ...dataCheckboxesForm.$isInvalidElements,
            ...dataRadioButtonsForm.$isInvalidElements,
            ...dataFilesForm.$isInvalidElements,
            ...dataSwitchesForm.$isInvalidElements,
            ...(dataValidator.$isInvalidElements || [])
        ],
        data: {
            ...data,
            ...(dataValidator.data || {})
        }
    };
}


function matchesAll(value, ...regexes) {
    for(const regex of regexes) {
        if(!new RegExp(regex).test(value))
            return false;
    }

    return true;
}

/**
 *
 * @param {jQuery} $modal jQuery modal
 * @param {boolean} isAttachmentForm
 * @param $modal jQuery modal
 * @param {Object.<*,*>} data
 * @return {{errorMessages: Array<string>, success: boolean, $isInvalidElements: Array<*>}}
 */
function processInputsForm($modal, data, isAttachmentForm) {
    const $inputs = $modal.find('.data:not([name^="savedFiles"]):not([type=radio])');
    const $isInvalidElements = [];
    const missingInputNames = [];

    const errorMessages = [];

    const $firstDate = $inputs.filter('.date.first-date');
    const $lastDate = $inputs.filter('.date.last-date');
    const firstDate = $firstDate.val();
    const lastDate = $lastDate.val();

    if (($firstDate.length > 0 && $lastDate.length > 0)
        && (
            !firstDate ||
            !lastDate ||
            moment(lastDate, 'D/M/YYYY h:mm').isSameOrBefore(moment(firstDate, 'D/M/YYYY h:mm'))
        )) {
        errorMessages.push('La date de début doit être antérieure à la date de fin.');
        $isInvalidElements.push($firstDate, $lastDate);
    }
    const dataPhonesInvalid = {};

    $inputs.each(function () {
        const $input = $(this);
        const name = $input.attr('name');

        let $formGroupLabel = $input.closest('.form-group').find('label');
        if (!$formGroupLabel.exists()) {
            $formGroupLabel = $input.closest('label').find('.wii-field-name');
        }
        const $editorContainer = $input.siblings('.ql-container');
        const $qlEditor = $editorContainer.length > 0
            ? $editorContainer.find('.ql-editor')
            : undefined;

        let val = $qlEditor && $qlEditor.length > 0
            ? $qlEditor.prop('innerHTML')
            : $input.val();
        val = (val && typeof val.trim === 'function') ? val.trim() : val;

        const customLabel = $input.data('custom-label');
        // Fix bug when we write <label>Label<select>...</select></label
        // the label variable had text options
        const dirtyLabel = (
            customLabel
            || $formGroupLabel
                .clone()    //clone the element
                .children() //select all the children
                .remove()   //remove all the children
                .end()      //again go back to selected element
                .text()
        );

        // on enlève l'éventuelle * du nom du label
        const label = (dirtyLabel || '')
            .replace(/\*/g, '')
            .replace(/\n/g, ' ')
            .trim();

        // validation données obligatoires
        if (($input.hasClass('needed') && $input.is(`:not([type=radio])`))
            && $input.is(':disabled') === false
            && (val === undefined
                || val === ''
                || val === null
                || (Array.isArray(val) && val.length === 0)
                || ($qlEditor && $qlEditor.length > 0 && !$qlEditor.text()))) {

            if($input.data(`label`)) {
                missingInputNames.push($input.data(`label`));
            } else if ($input.prev('label').text()){
                missingInputNames.push($input.prev('label').text().replace('*', ''));
            }else if ($input.closest('label').find(`.wii-field-name`)){
                missingInputNames.push(label);
            } else if (label && missingInputNames.indexOf(label) === -1) {
                missingInputNames.push(label);
            }

            $isInvalidElements.push($input, $input.next().find('.select2-selection'));
            if ($editorContainer.length > 0) {
                $isInvalidElements.push($editorContainer);
            }
        }
        else if ($input.hasClass('is-barcode')
            && !isBarcodeValid($input)) {
            errorMessages.push(`Le champ ${label} doit contenir au maximum 24 caractères, lettres ou chiffres uniquement, pas d’accent.`);
            $isInvalidElements.push($input);
            if ($input.is(':not(input)')) {
                $isInvalidElements.push($input.parent());
            }
        }
        // validation valeur des inputs de type password
        else if ($input.attr('type') === 'password' && $input.attr('name') === 'password') {
            let password = $input.val();
            let isNotChanged = $input.hasClass('optional-password') && password === "";
            if (!isNotChanged) {
                if (password.length < 8) {
                    errorMessages.push('Le mot de passe doit faire au moins 8 caractères.');
                    $isInvalidElements.push($input)
                } else if (matchesAll(/[A-Z]/, /[a-z]/, /\d/, /\W|_/)) {
                    errorMessages.push('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial (@ $ ! % * ? & + . _ ).');
                    $isInvalidElements.push($input)
                } else {
                    saveData($input, data, name, val, isAttachmentForm);
                }
            }
            else {
                saveData($input, data, name, val, isAttachmentForm);
            }
        }
        // validation valeur des inputs de type number
        else if ($input.attr('type') === 'number') {
            if (val
                && !$input.is(':disabled')) {
                let val = parseInt($input.val());
                let min = parseInt($input.attr('min'));
                let max = parseInt($input.attr('max'));
                if (!isNaN(val)
                    && (
                        val > max
                        || val < min
                    )) {
                    let errorMessage = 'La valeur du champ ' + label;
                    if (!isNaN(min) && !isNaN(max)) {
                        errorMessage += min > max
                            ? ` doit être inférieure à ${max}.`
                            : ` doit être comprise entre ${min} et ${max}.`;
                    } else if (!isNaN(max)) {
                        errorMessage += ` doit être inférieure à ${max}.`;
                    } else if (!isNaN(min)) {
                        errorMessage += ` doit être supérieure à ${min}.`;
                    } else {
                        errorMessage += ` ne peut pas être rempli`;
                    }
                    errorMessages.push(errorMessage)
                    $isInvalidElements.push($input);
                } else {
                    saveData($input, data, name, val, isAttachmentForm);
                }
            }
            else {
                saveData($input, data, name, val, isAttachmentForm);
            }
        }
        else if ($input.attr('type') === 'checkbox') {
            saveData($input, data, name, Number($input.prop('checked')), isAttachmentForm);
        }
        else if ($input.hasClass('phone-number') && !dataPhonesInvalid && !$input.data('iti').isValidNumber()) {
            if (!dataPhonesInvalid[name]) {
                dataPhonesInvalid[name] = true;
            }
        } else {
            if ($editorContainer.length > 0) {
                const maxLength = parseInt($input.attr('max'));
                if (maxLength) {
                const $commentStrWithoutTag = $qlEditor.text();
                    if ($commentStrWithoutTag.length > maxLength) {
                        errorMessages.push(Translation.of('Général', '', 'Modale', 'Le commentaire excède les {1} caractères maximum.',{1: maxLength}, false));
                    }
                    else {
                        saveData($input, data, name, val, isAttachmentForm);
                    }
                }
                else {
                    saveData($input, data, name, val, isAttachmentForm);
                }
            }
            else {
                saveData($input, data, name, val, isAttachmentForm);
            }
        }
    });

    const dataArrayPhonesInvalid = Object.keys(dataPhonesInvalid).reduce(
        (acc, currentName) => {
            if (dataPhonesInvalid[currentName] === true) {
                acc.push(currentName);
            }
            return acc;
        },
        []
    );

    if (dataArrayPhonesInvalid.length > 0) {
        errorMessages.push('Un ou plusieurs numéros de téléphones fournis sont invalides.');
        $isInvalidElements.push(...dataArrayPhonesInvalid.map((name) => $(`.data-array.phone-number[name="${name}"]`)));
    }

    if (missingInputNames.length > 0) {
        errorMessages.push(missingInputNames.length === 1
            ? Translation.of('Général', '', 'Modale', 'Veuillez renseigner le champ : {1}', {1 : missingInputNames[0]}, false)
            : Translation.of('Général', '', 'Modale', 'Veuillez renseigner les champs : {1}', {1 : missingInputNames.join(', ')}, false)
        );
    }

    const success = $isInvalidElements.length === 0;
    if (!success && errorMessages.length === 0) {
        errorMessages.push('Une erreur est présente dans le formulaire');
    }

    return {
        success,
        errorMessages,
        $isInvalidElements,
        data
    };
}

/**
 *
 * @param $modal jQuery modal
 * @param {Object.<*,*>} data
 * @param {boolean} isAttachmentForm
 * @return {{errorMessages: Array<string>, success: boolean, $isInvalidElements: Array<*>}}
 */
function processCheckboxesForm($modal, data, isAttachmentForm) {
    const $checkboxes = $modal.find('.checkbox');

    $checkboxes.each(function () {
        const $input = $(this);
        if (!$input.hasClass("no-data")) {
            saveData($input, data, $input.attr("name"), $input.is(':checked'), isAttachmentForm);
        }
    });

    return {
        success: true,
        errorMessages: [],
        $isInvalidElements: []
    };
}

/**
 *
 * @param $modal jQuery modal
 * @param {Object.<*,*>} data
 * @param {boolean} isAttachmentForm
 * @return {{errorMessages: Array<string>, success: boolean, $isInvalidElements: Array<*>}}
 */
function processRadioButtonsForm($modal, data, isAttachmentForm) {
    const $radioButtons = $modal.find('input[type=radio]:checked.data');

    $radioButtons.each(function () {
        const $radio = $(this);
        saveData($radio, data, $radio.attr("name"), $radio.val(), isAttachmentForm);
    });

    return {
        success: true,
        errorMessages: [],
        $isInvalidElements: []
    };
}

/**
 *
 * @param $modal jQuery modal
 * @param {Object.<*,*>} data
 * @param {boolean} isAttachmentForm
 * @return {{errorMessages: Array<string>, success: boolean, $isInvalidElements: Array<*>}}
 */
function processSwitchesForm($modal, data, isAttachmentForm) {
    const $switches = $modal.find('.wii-switch, .wii-switch-no-style');
    const $invalidElements = [];
    const messages = [];

    $switches.each(function () {
        const $div = $(this);
        const $input = $div.find('input:checked');

        if($div.hasClass("needed") && $input.length === 0) {
            $invalidElements.push($div);
            messages.push("Veuillez renseigner une valeur pour le champ " + $div.data("title"));
        } else {
            saveData($input, data, $input.attr("name"), $input.val(), isAttachmentForm);
        }
    });

    return {
        success: $invalidElements.length === 0,
        errorMessages: messages,
        $isInvalidElements: $invalidElements
    };
}

/**
 *
 * @param {jQuery} $modal jQuery modal
 * @param {Object.<*,*>} data
 * @return {{errorMessages: Array<string>, success: boolean, $isInvalidElements: Array<*>}}
 */
function processFilesForm($modal, data) {
    const $requiredFileField = $modal.find('input[name="isFileNeeded"][type="hidden"]');
    const required = $requiredFileField.val() === '1';

    $.each(droppedFiles, function(index, file) {
        data[`file${index}`] = file;
    });

    const $savedFiles = $modal.find('.data[name="savedFiles[]"]');
    if ($savedFiles.length > 0) {
        $savedFiles.each(function (index, field) {
            data[`files[${index}]`] = $(field).val();
        });
    }

    const $dataFiles = $modal.find('.data-file');
    if ($dataFiles.length > 0) {
        $dataFiles.each(function (index, field) {
            const $field = $(field);
            const files = $field[0].files;
            const fieldName = $field.attr('name');
            if(!$field.is('[multiple]')) {
                data[fieldName] = files[0];
            } else {
                for(let fileIndex = 0; fileIndex < files.length; fileIndex++) {
                    data[`${fieldName}[${fileIndex}]`] = files[fileIndex];
                }
            }
        });
    }

    const isInvalidRequired = required && droppedFiles.length === 0 && $savedFiles.length === 0;

    return {
        success: !isInvalidRequired,
        errorMessages: isInvalidRequired ? [Translation.of('Général', '', 'Modale', 'Vous devez ajouter au moins une pièce jointe.')] : [],
        $isInvalidElements: isInvalidRequired ? [$modal.find('.dropFrame')] : []
    };
}

/**
 * @param $modal jQuery modal
 * @param {Object.<*,*>} data
 * @return {{errorMessages: Array<string>, success: boolean, $isInvalidElements: Array<*>}}
 */
function processDataArrayForm($modal, data) {
    const $inputsArray = $modal.find(".data-array");

    const noStringify = $modal.find(".data-array[data-no-stringify]").length > 0;

    const dataArray = {};
    const dataArrayNeedPositive = {};
    const dataPhonesInvalid = {};
    const $isInvalidElements = [];

    const errorMessages = [];
    const customNeededPositiveErrors = {};

    $inputsArray.each(function () {
        const $input = $(this);
        const type = $input.attr('type')
        const name = $input.attr('name');
        let val = type === 'number'
            ? Number($input.val())
            : ($input.hasClass('phone-number')
                ? $input.data('iti').getNumber()
                : $input.val()
            );
        if ($input.data('id')) {
            if (val) {
                if (!dataArray[name]) {
                    dataArray[name] = {};
                }
                dataArray[name][$input.data('id')] = val;
            }
        } else {
            if(val) {
                const name = $input.attr("name");
                if (!dataArray[name]) {
                    dataArray[name] = [];
                }
                dataArray[name].push(val);
            }
        }
        if (type === 'number' && $input.hasClass('needed-positiv')) {
            const customName = $input.data("custom-label") || name
            if (!dataArrayNeedPositive[customName]) {
                dataArrayNeedPositive[customName] = 0;
            }
            dataArrayNeedPositive[customName] += val;
            if ($input.data("custom-needed-positiv-error")) {
                customNeededPositiveErrors[customName] = $input.data("custom-needed-positiv-error");
            }
        } else if ($input.hasClass('phone-number') && !$input.data('iti').isValidNumber()) {
            if (!dataPhonesInvalid[name]) {
                dataPhonesInvalid[name] = true;
            }
        }
    });

    const dataArrayNeedPositiveNames = Object.keys(dataArrayNeedPositive).reduce(
        (acc, currentName) => {
            if (dataArrayNeedPositive[currentName] === 0) {
                acc.push(currentName);
            }
            return acc;
        },
        []
    );

    const dataArrayPhonesInvalid = Object.keys(dataPhonesInvalid).reduce(
        (acc, currentName) => {
            if (dataPhonesInvalid[currentName] === true) {
                acc.push(currentName);
            }
            return acc;
        },
        []
    );
    if (dataArrayNeedPositiveNames.length > 0) {
        errorMessages.push(...dataArrayNeedPositiveNames.map((name) => (
            customNeededPositiveErrors[name]
            || Translation.of('Général', '', 'Modale', 'Veuillez renseigner au moins un {1}', {1: name})
        )));
        $isInvalidElements.push(...dataArrayNeedPositiveNames.map((name) => ($(`.data-array.needed-positiv[data-custom-label="${name}"]`) || $(`.data-array.needed-positiv[name="${name}"]`))));
    }
    if (dataArrayPhonesInvalid.length > 0) {
        errorMessages.push('Un ou plusieurs numéros de téléphones fournis sont invalides.');
        $isInvalidElements.push(...dataArrayPhonesInvalid.map((name) => $(`.data-array.phone-number[name="${name}"]`)));
    }

    for(const currentName in dataArray) {
        data[currentName] = !noStringify ? JSON.stringify(dataArray[currentName]) : dataArray[currentName];
    }
    return {
        success: $isInvalidElements.length === 0,
        errorMessages,
        $isInvalidElements
    };
}

function createFormData(object) {
    const formData = new FormData();
    Object
        .keys(object)
        .forEach((key) => {
            formData.append(key, object[key]);
        });
    return formData;
}


/**
 * Check if value in the given jQuery input is a valid barcode
 * @param $input
 * @return {boolean}
 */
function isBarcodeValid($input) {
    /** Constants which define a valid barcode */
    const regex = new RegExp($('#BARCODE_VALID_REGEX').val());
    const value = $input.val();
    return Boolean(!value || regex.test(value));
}

/**
 * Display error message and error put field in error
 * @param {*} $modal jQuery element of the modal
 * @param {{$isInvalidElements: *|undefined, errorMessages: string[]|undefined, keepModal: boolean|undefined}} options jQuery elements in error, errorMessages error messages
 */
function displayFormErrors($modal, {$isInvalidElements, errorMessages, keepModal} = {}) {
    if ($isInvalidElements) {
        $isInvalidElements.forEach(($field) => {
            $field.addClass(FORM_INVALID_CLASS);
        });
    }

    const filledErrorMessages = (errorMessages || []).filter(Boolean);
    if (filledErrorMessages.length > 0) {
        const $message = filledErrorMessages.join('<br/>');
        const $innerModalMessageError = $modal.find(`.${FORM_ERROR_CONTAINER}`);
        if ($innerModalMessageError.length > 0) {
            $innerModalMessageError.addClass("p-4");
            $innerModalMessageError.html($message);
        } else {
            showBSAlert($message, 'danger');
            if (!keepModal) {
                $modal.modal('hide');
            }
        }
    }
}

function displayAttachements(files, $dropFrame, isMultiple = true) {

    const errorMessages = [];

    const $fileBag = $dropFrame.siblings('.file-bag');

    if (!isMultiple) {
        $fileBag.empty();
    }

    $.each(files, function(index, file) {
        let formatValid = checkFileFormat(file, $dropFrame);
        let sizeValid = checkSizeFormat(file, $dropFrame);

        if (!formatValid) {
            errorMessages.push(Translation.of('Général', '', 'Modale', '"{1}" : Le format de votre pièce jointe n\'est pas supporté. Le fichier doit avoir une extension.',{1: file.name}));
        } else if (!sizeValid) {
            errorMessages.push(Translation.of('Général', '', 'Modale', '"{1}" : La taille du fichier ne doit pas dépasser 10 Mo.',{1: file.name}));
        } else {
            let fileName = file.name;

            let reader = new FileReader();
            reader.addEventListener('load', function () {
                let icon = `fa-file`;
                if($fileBag.is(`[data-icon]`)) {
                    icon = $fileBag.data(`icon`);
                }

                $fileBag.append(`
                    <p class="attachement" value="` + withoutExtension(fileName) + `">
                        <a target="_blank" href="` + reader.result + `" class="has-tooltip" title="${fileName}">
                            <i class="fa ${icon} mr-2"></i>` + fileName + `
                        </a>
                        <i class="fa fa-times red pointer" onclick="removeAttachment($(this))"></i>
                    </p>`);
            });
            reader.readAsDataURL(file);
        }
    });

    if (errorMessages.length === 0) {
        displayRight($dropFrame);
        clearErrorMsg($dropFrame);
    } else {
        displayWrong($dropFrame);
        displayFormErrors($dropFrame.closest('.modal'), {errorMessages});
    }
}

function withoutExtension(fileName) {
    let array = fileName.split('.');
    return array[0];
}

function removeAttachment($elem) {
    let deleted = false;
    let fileName = $elem.closest('.attachement').find('a').first().text().trim();
    $elem.closest('.attachement').remove();
    droppedFiles.forEach(file => {
        if (file.name === fileName && !deleted) {
            deleted = true;
            droppedFiles.splice(droppedFiles.indexOf(file), 1);
        }
    });
}

function checkFileFormat(file) {
    return file.name.includes('.') !== false;
}

function checkSizeFormat(file) {
    return file.size < FILE_MAX_SIZE;
}

function dragEnterDiv(event, div) {
    displayWrong(div);
}

function dragOverDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    displayWrong(div);
    return false;
}

function dragLeaveDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    displayNeutral(div);
    return false;
}

function openFileExplorer(span) {
    span.siblings('.fileInput').trigger('click');
}

function saveDroppedFiles(event, $div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            event.preventDefault();
            event.stopPropagation();
            let files = Array.from(event.dataTransfer.files);

            const $inputFile = $div.find('.fileInput');
            saveInputFiles($inputFile, files);
        }
    }
    else {
        displayWrong($div);
    }
    return false;
}

function saveInputFiles($inputFile, files) {
    let filesToSave = files || $inputFile[0].files;
    const isMultiple = $inputFile.prop('multiple');

    Array.from(filesToSave).forEach(file => {
        if (checkSizeFormat(file) && checkFileFormat(file)) {
            if (!isMultiple) {
                droppedFiles = [];
            }
            droppedFiles.push(file);
        }
    });

    let dropFrame = $inputFile.closest('.dropFrame');

    displayAttachements(filesToSave, dropFrame, isMultiple);
    $inputFile[0].value = '';
}

function resetDroppedFiles() {
    droppedFiles = [];
}

function saveData($input, data, name, val, isAttachmentForm) {
    const $parent = $input.closest('[data-multiple-key]');
    if (name) {
        if ($parent && $parent.length > 0) {
            const multipleKey = $parent.data('multiple-key');
            const objectIndex = $parent.data('multiple-object-index');
            const multipleValue = data[multipleKey]
                ? (isAttachmentForm
                    ? JSON.parse(data[multipleKey])
                    : data[multipleKey])
                : {};
            multipleValue[objectIndex] = (multipleValue[objectIndex] || {});

            multipleValue[objectIndex][name] = $input.hasClass('list-multiple')
                ? JSON.stringify(val)
                : multipleValue[objectIndex][name] = val;

            data[multipleKey] = isAttachmentForm
                ? JSON.stringify(multipleValue)
                : multipleValue;
        } else if ($input.hasClass('list-multiple')) {
            data[name] = JSON.stringify(val);
        } else {
            data[name] = val;
        }
    }
}
