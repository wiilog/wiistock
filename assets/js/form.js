import WysiwygManager from "@app/wysiwyg-manager";
import Flash from "@app/flash";
import AJAX from "@app/ajax";

export default class Form {

    static FormCounter = 0;

    id;
    element;
    submitListeners;
    openListeners;
    closeListeners;
    processors = [];
    uploads = {};

    constructor(id) {
        this.id = id;
    }

    static create(selector, {clearOnOpen, submitButtonSelector} = {}) {
        const $form = $(selector);
        let form = $form.data('form');

        if (!form || !(form instanceof Form)) {
            form = new Form(++Form.FormCounter);

            form
                .clearOpenListeners()
                .clearCloseListeners()
                .clearSubmitListeners()
                .clearProcessors();

            form.element = $form;
            $form
                .data('form', form)
                .data('form-id', form.id)
                .attr('data-form-id', form.id);

            WysiwygManager.initializeWYSIWYG(form.element);
            submitButtonSelector ??= '[type=submit]';
            form.element
                .off('click.submit-form')
                .off('shown.bs.modal')
                .off('hidden.bs.modal')
                .on(`click.submit-form`, submitButtonSelector, function (event) {
                    const result = Form.process(form, {
                        button: $(this),
                    });

                    if (result) {
                        form.submitListeners.forEach((submitListener) => {
                            submitListener(result, form);
                        });
                    }

                    event.preventDefault();
                })
                .on('shown.bs.modal', function (event) {
                    if (clearOnOpen) {
                        form.clear();
                    }

                    form.openListeners.forEach((openListener) => {
                        openListener(event);
                    });
                })
                .on('hidden.bs.modal', function (event) {
                    form.closeListeners.forEach((closeListener) => {
                        closeListener(event);
                    });
                });
        }

        return form;
    }

    addProcessor(callback = null) {
        this.processors.push(callback);
        return this;
    }

    onOpen(callback = null) {
        if (callback) {
            this.openListeners.push(callback);
        }
        return this;
    }

    onClose(callback = null) {
        if (callback) {
            this.closeListeners.push(callback);
        }
        return this;
    }

    onSubmit(callback = null) {
        if (callback) {
            this.submitListeners.push(callback);
        }
        return this;
    }

    /**
     * @param {"GET"|"POST"|"PUT"|"PATCH"|"DELETE"} method HTTP method
     * @param {string} route Symfony route name
     * @param {{
     *    keepModal?: boolean,
     *    success?: function,
     *    error?: function
     *    routeParams?: {[string]: string},
     *    tables?: Datatable|Datatable[],
     *    clearFields?: boolean,
     * }} options
     * @returns {Form}
     */
    submitTo(method, route, options = {}) {
        this.onSubmit((data, form) => {
            form.loading(
                () => AJAX.route(method, route, options.routeParams || {})
                    .json(data)
                    .then(response => {
                        if(response.success) {
                            if (this.element.is('.modal')) {
                                if (!options.keepModal) {
                                    this.element.modal(`hide`);
                                }
                            }

                            if(options.success) {
                                options.success(response);
                            }

                            if (options.clearFields) {
                                form.clear();
                            }

                            if(options.tables) {

                                const tables = Array.isArray(options.tables)
                                    ? options.tables
                                    : [options.tables];

                                tables.forEach((table) => {
                                    if (table instanceof Function) {
                                        table().ajax.reload();
                                    } else {
                                        table.ajax.reload();
                                    }
                                })
                            }
                        } else if (options.error){
                            options.error(response)
                        }
                    })
            )
        })

        return this;
    }

    clearOpenListeners() {
        this.openListeners = [];
        return this;
    }

    clearCloseListeners() {
        this.closeListeners = [];
        return this;
    }

    clearSubmitListeners() {
        this.submitListeners = [];
        return this;
    }

    clearProcessors() {
        this.processors = [];
        return this;
    }

    clear() {
        clearFormError(this);
        clearModal(this.element);
    }

    on(event, selector, callback) {
        this.element.on(event, selector, callback);
        return this;
    }

    process(config = {}) {
        return Form.process(this, config);
    }

    /**
     * Launch loading on submit button of the form and wait for the given promise
     * @param {function} action Function returning a promise to wait
     * @param {boolean} endLoading default to true
     * @param {{
     *    closeModal: boolean|undefined,
     * }} options
     */
    loading(action, endLoading = true, options = {}) {
        const $submit = this.element.find(`[type=submit]`);
        wrapLoadingOnActionButton($submit, action, endLoading);
        if (this.element.is(`.modal`)) {
            if (options.closeModal) {
                this.element.modal(`hide`);
            }
        }
    }

    static getFieldNames(form, config = {}) {
        const fieldNames = [];
        config.classes = config.classes || {data: `data`, array: `data-array`};

        eachInputs(form, config, ($input) => {
            const $multipleKey = $input.closest(`[data-multiple-key]`);
            if ($multipleKey.exists()) {
                fieldNames.push($multipleKey.data(`multiple-key`));
            } else {
                fieldNames.push($input.attr(`name`) || $input.attr(`data-wysiwyg`));
            }
        });

        // distinct
        return fieldNames.filter((field, index, self) => self.indexOf(field) === index);
    }

    static process(form, config = {}) {
        const errors = [];
        const data = new FormData();

        config.classes = config.classes || {data: `data`, array: `data-array`};

        clearFormError(form);

        eachInputs(form, config, ($input, value) => {
            treatInputError($input, errors, form);

            const $multipleKey = $input.closest(`[data-multiple-key]`);
            if ($multipleKey.exists()) {
                const multipleKey = JSON.parse(data.get($multipleKey.data(`multiple-key`)) || `{}`);
                if (!multipleKey[$multipleKey.data(`multiple-object-index`)]) {
                    multipleKey[$multipleKey.data(`multiple-object-index`)] = {};
                }

                const multipleObject = multipleKey[$multipleKey.data(`multiple-object-index`)];
                multipleObject[$input.attr(`name`) || $input.attr(`data-wysiwyg`)] = serializeFormValue(value);
                data.set($multipleKey.data(`multiple-key`), JSON.stringify(multipleKey));
            } else {
                data.set($input.attr(`name`) || $input.attr(`data-wysiwyg`), serializeFormValue(value));
            }
        });

        const $form = getFormElement(form);
        Form.addDataArray($form, data, config.classes);

        processFiles($form, data, errors);
        if(config.button && config.button.attr(`name`)) {
            data.append(config.button.attr(`name`), config.button.val());
        }

        if(form instanceof Form) {
            for(const processor of form.processors) {
                processor(data, errors, $form);
            }
        }

        if(errors.length > 0) {
            console.error(`%cForm errors (${errors.length}) %c`, ...[
                `font-weight: bold;`,
                `font-weight: normal;`,
            ], errors);
            if (errors[0].elements && errors[0].elements[0]) {
                const $firstInvalidElement = errors[0].elements[0];
                const $scrollableParent = $firstInvalidElement.parents(`.modal`).exists()
                    ? $firstInvalidElement.parents(`.modal`).first()
                    : $firstInvalidElement.parents(`body`);

                if ($scrollableParent) {
                    $scrollableParent.animate({
                        scrollTop: $firstInvalidElement.offset()?.top
                    }, 1000);
                }
            }
        }

        if(config.ignoreErrors) {
            return data;
        }

        const globalErrors = [];

        // display errors under each field
        for(const error of errors) {
            if (error.elements && error.elements.length > 0) {
                Form.showInvalidFields(error.elements, !error.global ? error.message : undefined)
                if (error.global) {
                    globalErrors.push(error.message);
                }
                else {
                    const currentGlobalErrors = Form.getInvalidGlobalErrors(error.elements, error.message);
                    if (currentGlobalErrors.length > 0) {
                        globalErrors.push(...currentGlobalErrors);
                    }
                }
            }
            else {
                // remove duplicate messages
                if (error.message && globalErrors.indexOf(error.message) === -1) {
                    globalErrors.push(error.message);
                }
            }
        }

        // Remove global duplicates errors and show
        const cleanedGlobalErrors = globalErrors.filter((message, index) => message && globalErrors.indexOf(message) === index);
        for(const message of cleanedGlobalErrors) {
            Flash.add(`danger`, message);
        }

        return errors.length === 0 ? data : false;
    }

    static addDataArray($form, data, classes) {
        const $arrays = $form.find(`select.${classes.array}, input.${classes.array}`);
        const grouped = {};
        for(const element of $arrays) {
            if(grouped[element.name] === undefined) {
                grouped[element.name] = [];
            }

            grouped[element.name].push(element);
        }

        for(const [name, elements] of Object.entries(grouped)) {
            data.append(name, elements
                .map(elem => $(elem))
                .map($elem => {
                    if($elem.attr(`type`) === `checkbox`) {
                        return $elem.is(`:checked`) ? $elem.val() : null;
                    } else {
                        return formatInputValue($elem);
                    }
                })
                .filter(val => val !== null));
        }
    }

    static showInvalidFields(elements, message) {
        if (message) {
            elements.forEach(($field) => {
                const {$formField, $dataField, $parent} = Form.getErrorConf($field)

                $formField.addClass(`is-invalid`);
                $parent.find(`.invalid-feedback`).remove();

                if (!$dataField.is(`[data-global-error]`)) {
                    $parent.append(`<span class="invalid-feedback d-inline-block">${message}</span>`);
                }
            });
        }
    }

    static getInvalidGlobalErrors(elements, message) {
        const errors = [];
        if (message) {
            elements.forEach(($field) => {
                const {label, $dataField} = Form.getErrorConf($field)
                const prefixMessage = label ? `${label} : ` : '';

                if ($dataField.is(`[data-global-error]`)) {
                    errors.push(`${prefixMessage}${message}`);
                }
            });
        }
        return errors;
    }

    static getErrorConf($field) {
        let $parent;
        if($field.is(`[data-s2-initialized]`)) {
            $field = $field.parent().find(`.select2-selection`);
        } else if($field.is(`[type="file"]`)) {
            $field = $field.siblings('.btn');
        }

        if($field.is(`[data-wysiwyg]`)) {
            $parent = $field.parent();
        } else {
            $parent = $field.closest(`label, .wii-checkbox, .wii-radio-container, .dropFrame`);
        }

        const $dataField = $field.is(`.select2-selection`)
            ? $field.closest(`.select2-container`).siblings(`select`)
            : $field;

        let label = $dataField.data(`global-error`)
            || $parent.find(`.field-label`).text()
            || $dataField.data('field-label');

        label = (label || '')
            .trim()
            .replace(/\*$/, '');

        return {
            label,
            $parent,
            $dataField, // base field like select for select2 elements
            $formField: $field, // displayed element
        };
    }

}

FormData.prototype.asObject = function() {
    const object = {};
    this.forEach((value, key) => {
        object[key] = value;
    });

    return object;
}

FormData.prototype.appendAll = function(object) {
    for(const [key, value] of Object.entries(object)) {
        this.append(key, value);
    }

    return object;
}

FormData.fromObject = function(object) {
    const data = new FormData();
    for(const [key, value] of Object.entries(object)) {
        data.append(key, value);
    }

    return data;
}

function ignoreInput($form, $input, config) {
    const $closestFormParent = $input.closest('[data-form-id]');
    const closestFormId = $closestFormParent.data('form-id');
    const formId = $form.data('form-id');
    return (
        formId !== closestFormId
        || ($input.is(`:not(.force-data, [type="hidden"]):hidden`)
            && !$input.closest(`.wii-switch, .wii-expanded-switch`).is(`:visible`))
        || (config.ignored
            && ($input.is(config.ignored) || $input.closest(config.ignored).exists()))
        || (!$input.attr(`name`)
            && !$input.attr(`data-wysiwyg`))
    );
}

function eachInputs(form, config, callback) {
    const classes = config.classes;
    const $form = getFormElement(form);
    const $visibleForm = $form.find(':not(.d-none)');
    const $inputs = $form
        .find(`
            .fileInput,
            .wii-switch,
            .wii-switch-no-style,
            select.${classes.data},
            input.${classes.data},
            input.${classes.array},
            input[data-repeat],
            textarea.${classes.data},
            .data[data-wysiwyg]`
        );
    for(const input of $inputs) {
        let $input = $(input);

        if (ignoreInput($form, $input, config)) {
            continue;
        }

        if($input.attr(`type`) === `radio`) {
            const $checked = $form.find(`input[type="radio"][name="${input.name}"]:checked`);
            if($checked.exists()) {
                $input = $checked;
            }
        }

        callback($input, formatInputValue($input));
    }
}

function treatInputError($input, errors, form) {
    if ($input.attr(`type`) === `number`) {
        let val = parseInt($input.val());
        let min = parseInt($input.attr('min'));
        let max = parseInt($input.attr('max'));

        if (!isNaN(val) && (val > max || val < min)) {
            let message = `La valeur `;
            if (!isNaN(min) && !isNaN(max)) {
                message += min > max
                    ? `doit être inférieure à ${max}.`
                    : `doit être comprise entre ${min} et ${max}.`;
            } else if (!isNaN(max)) {
                message += `doit être inférieure à ${max}.`;
            } else if (!isNaN(min)) {
                message += `doit être supérieure à ${min}.`;
            } else {
                message += `est invalide`;
            }

            errors.push({
                elements: [$input],
                message,
            });
        }
    }
    else if ($input.attr(`type`) === `tel`) {
        const regex = /^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})$/;
        if ($input.val() && !$input.val().match(regex)) {
            errors.push({
                elements: [$input],
                message: `Le numéro de ${$input.is(`[data-fax]`) ? `fax` : `téléphone`} n'est pas valide`,
            });
        }
    }
    else if ($input.attr(`type`) === `text`) {
        const val = $input.val().trim();
        const minLength = parseInt($input.attr('minlength'));
        const maxLength = parseInt($input.attr('maxlength'));

        if (val && minLength && val.length < minLength) {
            errors.push({
                elements: [$input],
                message: Translation.of('Général', '', 'Modale', "Le nombre de caractères de ce champ ne peut être inférieur à {1}.", {
                    1: minLength,
                }),
            });
        }
        else if (val && maxLength && val.length > maxLength) {
            errors.push({
                elements: [$input],
                message: Translation.of('Général', '', 'Modale', "Le nombre de caractères de ce champ ne peut être supérieur à {1}.", {
                    1: maxLength,
                }),
            });
        }
    }

    if ($input.data(`repeat`)) {
        const $form = getFormElement(form);
        const $toRepeat = $form.find(`input[name="${$input.data(`repeat`)}"`);

        if ($input.val() !== $toRepeat.val()) {
            errors.push({
                elements: [$input, $toRepeat],
                message: `Les champs ne sont pas identiques`,
            });
        }
    }

    if (($input.is(`[required]`) || $input.is(`[data-required]`) || $input.is(`.needed`))) {
        if (([`radio`, `checkbox`].includes($input.attr(`type`)) && !$input.is(`:checked`))) {
            const $elementInError = $input.closest(`.wii-radio-container, .wii-checkbox, .wii-switch, .wii-expanded-switch`);
            // check if element is already in error
            const elementAlreadyInError = errors.some(({elements}) => (
                elements
                && elements.some((el) => $(el).data('name') === $elementInError.data('name'))
            ));
            if (!elementAlreadyInError) {
                errors.push({
                    elements: [$elementInError],
                    message: `Vous devez sélectionner au moins un élément`,
                });
            }
        } else {
            let valueIsEmpty;
            if ($input.is(`[data-wysiwyg]:not(.wii-one-line-wysiwyg)`)) { // for wysuwyg fields
                valueIsEmpty = !$input.find(`.ql-editor`).text();
            } else if ($input.is(`.wii-one-line-wysiwyg`)) {
                valueIsEmpty = !$input.text();
            } else if ($input.is(`select[multiple]`) && Array.isArray($input.val())) { // for select2 multiple
                valueIsEmpty = $input.val().length === 0;
            } else if ($input.is(`[type="file"]`)) { // for input file
                valueIsEmpty = (!$input.val() && !$input.siblings('.preview-container').find('img').attr('src'));
            } else {
                valueIsEmpty = !$input.val();
            }

            if (valueIsEmpty) {
                errors.push({
                    elements: [$input],
                    message: `Ce champ est requis`,
                });
            }
        }
    }

    const htmlValidity = $input.get(0).validity;

    if (htmlValidity && !htmlValidity.valid) {
        // Object.keys doesn't work with HTML validty object ValidityState
        const validityKeys = [];
        for(const key in htmlValidity){
            if (key !== 'valid') {
                validityKeys.push(key);
            }
        }
        const message = validityKeys
            .filter((key) => htmlValidity[key])
            .map((key) => $input.data(`error-${key.toLowerCase()}`))
            .filter((message) => message)
            .join('<br/>');

        if (message) {
            errors.push({
                elements: [$input],
                message,
            });
        }
    }
}

function formatInputValue($input) {
    let value;
    if ($input.is(`[data-wysiwyg]`)) {
        const $qlEditor = $input.find(`.ql-editor`);
        const $wrapper = $qlEditor.exists() ? $qlEditor : $input;
        value = $wrapper.html();
    } else if ($input.attr(`type`) === `checkbox`) {
        value = $input.is(`:checked`) ? `1` : `0`;
    } else if ($input.attr(`type`) === `file`) {
        value = $input[0].files[0] || null;
    } else if ($input.attr(`type`) === `radio`) {
        value = $input.is(`:checked`)
            ? $input.val()
            : null;
    } else if (($input.attr(`type`) === `text` && $input.hasClass(`phone-number`))) {
        value = window.intlTelInputGlobals.getInstance($input[0])?.getNumber() || null;
    } else {
        value = $input.val() || null;
    }

    if ($input.parents('.free-field').exists() && Array.isArray(value)) {
        value = value.join(';');
    }

    if (typeof value === `string`) {
        value = value.trim();
    }
    return value;
}

function getFormElement(form) {
    return form instanceof Form ? form.element : form;
}

function processFiles($form, data, errors) {
    $.each(droppedFiles, function(index, file) {
        data.set(`file${index}`, file);
    });

    const $savedFiles = $form.find('.data[name="savedFiles[]"]');
    if ($savedFiles.length > 0) {
        $savedFiles.each(function (index, field) {
            data.set(`files[${index}]`, $(field).val());
        });
    } else {
        const $requiredFileField = $form.find('input[name="isFileNeeded"][type="hidden"]');
        const required = $requiredFileField.val() === '1';
        if(required && droppedFiles.length === 0) {
            errors.push({
                elements: [$requiredFileField.siblings('.dropFrame')],
                message: `Vous devez ajouter au moins un fichier`,
            });

            $requiredFileField.parent().addClass('is-invalid');
        }
    }

    const $dataFiles = $form.find('.data-file');
    if ($dataFiles.length > 0) {
        $dataFiles.each(function (index, field) {
            const $field = $(field);
            const files = $field[0].files;
            const fieldName = $field.attr('name');
            if(!$field.is('[multiple]')) {
                data.set(fieldName, files[0]);
            } else {
                for(let fileIndex = 0; fileIndex < files.length; fileIndex++) {
                    data.set(`${fieldName}[${fileIndex}]`, files[fileIndex]);
                }
            }
        });
    }
}

function clearFormError(form) {
    const $form = getFormElement(form);
    $form.find(`.is-invalid`).removeClass(`is-invalid`);
    $form.find(`.invalid-feedback`).remove();
}

export function formatIconSelector(state) {
    const $option = $(state.element);
    return $(`
        <span class="d-flex align-items-center">
            <img src="${$option.data('icon') || ''}" width="20px" height="20px" class="round mr-2"/>
            ${state.text}
        </span>
    `);
}

function serializeFormValue(value) {
    return (value === null || value === undefined)
        ? '' // value for clear the field
        : value;
}

