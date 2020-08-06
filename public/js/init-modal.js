const FORM_INVALID_CLASS = 'is-invalid';
const FORM_ERROR_CONTAINER = 'error-msg';

/**
 * Initialise une fenêtre modale
 *
 * Pour utiliser la validation des données :
 *      ajouter une <div class="error-msg"> à la fin du modal-body
 *      ajouter la classe "needed" aux inputs qui sont obligatoires
 *      supprimerle data-dismiss=modal du bouton submit de la modale (la gestion de la fermeture doit se faire dans cette fonction)
 *      pour un affichage optimal de l'erreur, le label et l'input doivent être dans une div avec la classe "form-group"
 *
 * @param {*} $modal jQuery element of the modal
 * @param {*} $submit jQuery element of the submit button
 * @param {string} path le chemin pris pour envoyer les données.
 * @param {{getFiles: undefined|function, tables: undefined|Array<*>, keepModal: undefined|boolean, keepForm: undefined|boolean, success: undefined|function, clearOnClose: undefined|boolean}} options Object containing some option.
 *   - getFiles return Array<File>
 *   - tables is an array of datatable
 *   - keepForm is an array of datatable
 *   - keepModal true if we do not close form
 *   - success success handler
 *   - clearOnClose clear the modal on close action
 *
 */
function InitModal($modal, $submit, path, options = {}) {
    if(options.clearOnClose) {
        $modal.on('hidden.bs.modal', function () {
            clearModal($modal);
        });
    }

    $submit
        .click(function () {
            submitAction($modal, path, options)
                .then((data) => {
                    if (data
                        && data.success
                        && options
                        && options.success) {
                        options.success(data);
                    }
                })
                .catch(() => {})
        });
}

/**
 *
 * @param {{getFiles: undefined|function, tables: undefined|Array<*>, keepModal: undefined|boolean, keepForm: undefined|boolean}} options Object containing some options.
 *   - getFiles return Array<File>
 *   - tables is an array of datatable
 *   - keepForm true if we do not clear form
 *   - keepModal true if we do not close form
 * @param {*} $modal jQuery element of the modal
 * @param {string} path
 */
function submitAction($modal, path, {getFiles, tables, keepModal, keepForm} = {}) {
    clearFormErrors($modal);
    const {success, errorMessages, $isInvalidElements, data} = processForm($modal, getFiles);

    if (success) {
        const smartData = getFiles
            ? createFormData(data)
            : JSON.stringify(data);

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
                if (data.success === false) {
                    displayFormErrors($modal, {
                        $isInvalidElements: data.invalidFieldsSelector ? [$(data.invalidFieldsSelector)] : undefined,
                        errorMessages: data.msg ? [data.msg] : undefined
                    });
                }
                else {
                    const res = treatSubmitActionSuccess($modal, data, tables, keepModal, keepForm);
                    if (!res) {
                        return;
                    }
                }

                return data;
            })
    }
    else {
        displayFormErrors($modal, {
            $isInvalidElements,
            errorMessages
        });

        return new Promise((_, reject) => {
            reject(false);
        });
    }
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
        .find('.editor-container')
        .css('border-top', '0px');

    $modal
        .find(`.${FORM_ERROR_CONTAINER}`)
        .empty();
}

function treatSubmitActionSuccess($modal, data, tables, keepModal, keepForm) {
    if (data.redirect) {
        window.location.href = data.redirect;
        return;
    }

    // pour mise à jour des données d'en-tête après modification
    if (data.entete) {
        $('.zone-entete').html(data.entete)
    }

    if (data.nextModal) {
        $modal.find('.modal-body').html(data.nextModal);
    }

    if (tables) {
        tables.forEach((table) => {
            table.ajax.reload(null, false);
        });
    }

    if (!data.nextModal && !keepModal) {
        $modal.modal('hide');
    }

    if (!keepForm) {
        clearModal($modal);
    }

    if (data.msg) {
        alertSuccessMsg(data.msg);
    }

    return true;
}

/**
 *
 * @param {*} $modal jQuery modal
 * @param {undefined|function} getFiles return Array<File>
 * @return {{errorMessages: Array<string>, success: boolean, data: FormData|Object.<*,*>, $isInvalidElements: Array<*>}}
 */
function processForm($modal, getFiles = undefined) {
    const dataArrayForm = processDataArrayForm($modal);
    const dataInputsForm = processInputsForm($modal);
    const dataCheckboxesForm = processCheckboxesForm($modal);
    const dataFilesForm = processFilesForm($modal, getFiles);

    // TODO remove ?
    const subData = {};
    $("div[name='id']").each(function () {
        subData[$(this).attr("name")] = $(this).attr('value');
    });

    return {
        success: (
            dataArrayForm.success
            && dataInputsForm.success
            && dataCheckboxesForm.success
            && dataFilesForm.success
        ),
        errorMessages: [
            ...dataArrayForm.errorMessages,
            ...dataInputsForm.errorMessages,
            ...dataCheckboxesForm.errorMessages,
            ...dataFilesForm.errorMessages
        ],
        $isInvalidElements: [
            ...dataArrayForm.$isInvalidElements,
            ...dataInputsForm.$isInvalidElements,
            ...dataCheckboxesForm.$isInvalidElements,
            ...dataFilesForm.$isInvalidElements
        ],
        data: {
            ...dataArrayForm.data,
            ...dataInputsForm.data,
            ...dataCheckboxesForm.data,
            ...dataFilesForm.data,
            ...subData
        }
    };
}

/**
 *
 * @param $modal jQuery modal
 * @return {{errorMessages: Array<string>, success: boolean, data: FormData|Object.<*,*>, $isInvalidElements: Array<*>}}
 */
function processInputsForm($modal) {
    const $inputs = $modal.find(".data");
    const data = {};
    const $isInvalidElements = [];
    const missingInputNames = [];

    const errorMessages = [];

    const saveData = ($input, name, val) => {
        const $parent = $input.closest('[data-multiple-key]');
        if ($parent && $parent.length > 0) {
            const multipleKey = $parent.data('multiple-key');
            const objectIndex = $parent.data('multiple-object-index');
            data[multipleKey] = (data[multipleKey] || {});
            data[multipleKey][objectIndex] = (data[multipleKey][objectIndex] || {});
            data[multipleKey][objectIndex][name] = val;
        } else {
            data[name] = val;
        }
    };

    const $firstDate = $inputs.filter('.date.first-date');
    const $lastDate = $inputs.filter('.date.last-date');
    const firstDate = $firstDate.val();
    const lastDate = $lastDate.val();

    if (($firstDate.length > 0 && $lastDate.length > 0)
        && (
            !firstDate ||
            !lastDate ||
            moment(firstDate, 'D/M/YYYY h:mm').isSameOrBefore(moment(lastDate, 'D/M/YYYY h:mm'))
        )) {
        errorMessages.push('La date de début doit être antérieure à la date de fin.');
        $isInvalidElements.push($firstDate, $lastDate);
    }

    const $limitSecurity = $inputs.filter('[name="limitSecurity"]');
    const $limitWarning = $inputs.filter('[name="limitWarning"]');
    const limitSecurity = $limitSecurity.val();
    const limitWarning = $limitWarning.val();

    if (($limitSecurity.length > 0 && $limitWarning.length > 0)
        && limitSecurity
        && limitWarning
        && limitWarning < limitSecurity) {
        errorMessages.push('Le seuil d\'alerte doit être supérieur au seuil de sécurité.');
        $isInvalidElements.push($limitSecurity, $limitWarning);
    }

    $inputs.each(function () {
        const $input = $(this);
        const name = $input.attr('name');
        let val = $input.val();
        val = (val && typeof val.trim === 'function') ? val.trim() : val;

        const $formGroupLabel = $input.closest('.form-group').find('label');

        // Fix bug when we write <label>Label<select>...</select></label
        // the label variable had text options
        const dirtyLabel = $formGroupLabel
            .clone()    //clone the element
            .children() //select all the children
            .remove()   //remove all the children
            .end()      //again go back to selected element
            .text();

        // on enlève l'éventuelle * du nom du label
        const label = (dirtyLabel || '')
            .replace(/\*/g, '')
            .replace(/\n/g, ' ')
            .trim();

        // validation données obligatoires
        if ($input.hasClass('needed')
            && (val === undefined
                || val === ''
                || val === null
                || (Array.isArray(val) && val.length === 0)
            )) {
            if ($input.is(':disabled') === false) {
                missingInputNames.push(label);
                $isInvalidElements.push($input, $input.next().find('.select2-selection'));
            }
        }
        else if ($input.hasClass('is-barcode')
            && !isBarcodeValid($input)) {
            errorMessages.push(`Le champ ${label} doit contenir au maximum 21 caractères (lettres ou chiffres uniquement).`);
            $isInvalidElements.push($input, $input.parent());
        }
        // validation valeur des inputs de type password
        else if ($input.attr('type') === 'password') {
            let password = $input.val();
            let isNotChanged = $input.hasClass('optional-password') && password === "";
            if (!isNotChanged) {
                if (password.length < 8) {
                    errorMessages.push('Le mot de passe doit faire au moins 8 caractères.');
                    $isInvalidElements.push($input)
                } else if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/)) {
                    errorMessages.push('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial (@$!%*?&).');
                    $isInvalidElements.push($input)
                } else {
                    saveData($input, name, password);
                }
            }
            else {
                saveData($input, name, password);
            }
        }
        // validation valeur des inputs de type number
        else if ($input.attr('type') === 'number') {
            if (!isNaN(val)
                && !$input.is(':disabled')) {
                let val = parseInt($input.val());
                let min = parseInt($input.attr('min'));
                let max = parseInt($input.attr('max'));
                if (val > max
                    || val < min) {
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
                    saveData($input, name, val);
                }
            }
            else {
                saveData($input, name, val);
            }
        }
        else {
            const $editorContainer = $input.siblings('.editor-container')
            if ($editorContainer.length > 0) {
                const maxLength = parseInt($input.attr('max'));
                if (maxLength) {
                    const $commentStrWithoutTag = $($input.val()).text();
                    if ($commentStrWithoutTag.length > maxLength) {
                        errorMessages.push(`Le commentaire excède les ${maxLength} caractères maximum.`);
                        $isInvalidElements.push($editorContainer);
                    }
                    else {
                        saveData($input, name, val);
                    }
                }
                else {
                    saveData($input, name, val);
                }
            }
            else {
                saveData($input, name, val);
            }
        }
    });

    if (missingInputNames.length > 0) {
        errorMessages.push(missingInputNames.length === 1
            ? `Veuillez renseigner le champ ${missingInputNames[0]}.`
            : `Veuillez renseigner les champs : ${missingInputNames.join(', ')}.`
        );
    }

    return {
        success: $isInvalidElements.length === 0,
        errorMessages,
        $isInvalidElements,
        data
    };
}

/**
 *
 * @param $modal jQuery modal
 * @return {{errorMessages: Array<string>, success: boolean, data: FormData|Object.<*,*>, $isInvalidElements: Array<*>}}
 */
function processCheckboxesForm($modal) {
    const $checkboxes = $modal.find('.checkbox');
    const data = {};

    $checkboxes.each(function () {
        const $input = $(this);
        if (!$input.hasClass("no-data")) {
            data[$input.attr("name")] = $input.is(':checked');
        }
    });

    return {
        success: true,
        errorMessages: [],
        $isInvalidElements: [],
        data
    };
}

/**
 *
 * @param {*} $modal jQuery modal
 * @param {function} getFiles jQuery modal
 * @return {{errorMessages: Array<string>, success: boolean, data: FormData|Object.<*,*>, $isInvalidElements: Array<*>}}
 */
function processFilesForm($modal, getFiles) {
    const data = {};
    if (getFiles) {
        const files = getFiles();

        $.each(files, function(index, file) {
            data[`file${index}`] = file;
        });
    }

    return {
        success: true,
        errorMessages: [],
        $isInvalidElements: [],
        data
    };
}

/**
 *
 * @param $modal jQuery modal
 * @return {{errorMessages: Array<string>, success: boolean, data: FormData|Object.<*,*>, $isInvalidElements: Array<*>}}
 */
function processDataArrayForm($modal) {
    const $inputsArray = $modal.find(".data-array");
    const dataArray = {};
    const dataArrayNeedPositive = {};
    const $isInvalidElements = [];

    const errorMessages = [];

    $inputsArray.each(function () {
        const $input = $(this);
        const type = $input.attr('type')
        const name = $input.attr('name');
        if (type === 'number') {
            const val = Number($input.val());
            if (val) {
                if (!dataArray[name]) {
                    dataArray[name] = {};
                }
                dataArray[name][$input.data('id')] = val;
            }

            if ($input.hasClass('needed-positiv')) {
                if (!dataArrayNeedPositive[name]) {
                    dataArrayNeedPositive[name] = 0;
                }
                dataArrayNeedPositive[name] += val;
            }
        }
        else {
            const name = $input.attr("name");
            const val = $input.val();
            if (!dataArray[name]) {
                dataArray[name] = [];
            }
            dataArray[name].push(val);
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

    if (dataArrayNeedPositiveNames.length > 0) {
        errorMessages.push(...dataArrayNeedPositiveNames.map((name) => `Veuillez renseigner au moins un ${name}.`));
        $isInvalidElements.push(...dataArrayNeedPositiveNames.map((name) => $(`.data-array.needed-positiv[name="${name}"]`)));
    }

    return {
        success: $isInvalidElements.length === 0,
        errorMessages,
        $isInvalidElements,
        data: Object.keys(dataArray).reduce((acc, currentName) => ({
            ...acc,
            [currentName]: JSON.stringify(dataArray[currentName])
        }), {})
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
    const value = $input.val();
    return Boolean(!value || BARCODE_VALID_REGEX.test(value));
}

/**
 * Display error message and error put field in error
 * @param {*} $modal jQuery element of the modal
 * @param {{$isInvalidElements: *|undefined, errorMessages: string[]|undefined}} options jQuery elements in error, errorMessages error messages
 */
function displayFormErrors($modal, {$isInvalidElements, errorMessages} = {}) {
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
            $innerModalMessageError.html($message);
        }
        else {
            alertErrorMsg($message, true)
        }
    }
}
