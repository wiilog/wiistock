import WysiwygManager from "./wysiwyg-manager";
import Flash from "./flash";
import AJAX, {POST} from "@app/ajax";

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

    static create(selector, {clearOnOpen} = {}) {
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
            form.element
                .off('click.submit-form')
                .off('shown.bs.modal')
                .off('hidden.bs.modal')
                .on(`click.submit-form`, '[type=submit]', function (event) {
                    console.error('huh');
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
                .on('shown.bs.modal', function () {
                    if (clearOnOpen) {
                        form.clear();
                    }

                    form.openListeners.forEach((openListener) => {
                        openListener();
                    });
                })
                .on('hidden.bs.modal', function () {
                    form.closeListeners.forEach((closeListener) => {
                        closeListener();
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
        console.log(callback);
        if (callback) {
            this.submitListeners.push(callback);
        }
        return this;
    }

    submitTo(method, route, options) {
        console.log("ok");
        this.onSubmit((data, form) => {
            console.log("hddd");
            form.loading(
                () => AJAX.route(method,route)
                    .json(data)
                    .then(response => {
                        if(response.success) {
                            this.element.modal(`hide`);

                            if(options.success) {
                                options.success(response);
                            }

                            if(options.table) {
                                options.table.ajax.reload();
                            }
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

    /**
     * Launch loading on submit button of the form and wait for the given promise
     * @param {function} action Function returning a promise to wait
     * @param {boolean} endLoading default to true
     */
    loading(action, endLoading = true) {
        const $submit = this.element.find(`[type=submit]`);
        wrapLoadingOnActionButton($submit, action, endLoading);
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
            if (value !== null) {
                if($input.is('[data-intl-tel-input]')){
                    $input.val(window.intlTelInputGlobals.getInstance($input[0]).getNumber());
                }
                const $multipleKey = $input.closest(`[data-multiple-key]`);
                if ($multipleKey.exists()) {
                    const multipleKey = JSON.parse(data.get($multipleKey.data(`multiple-key`)) || `{}`);
                    if (!multipleKey[$multipleKey.data(`multiple-object-index`)]) {
                        multipleKey[$multipleKey.data(`multiple-object-index`)] = {};
                    }

                    const multipleObject = multipleKey[$multipleKey.data(`multiple-object-index`)];
                    multipleObject[$input.attr(`name`) || $input.attr(`data-wysiwyg`)] = value;
                    data.set($multipleKey.data(`multiple-key`), JSON.stringify(multipleKey));
                } else {
                    data.set($input.attr(`name`) || $input.attr(`data-wysiwyg`), value);
                }
            }
        });

        const $form = getFormElement(form);
        Form.addDataArray($form, data, config.classes);

        processFiles($form, data);
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
                        scrollTop: $firstInvalidElement.offset().top
                    }, 1000);
                }
            }
        }

        if(config.ignoreErrors) {
            return data;
        }

        // display errors under each field
        for(const error of errors) {
            if (error.elements && error.elements.length > 0) {
                error.elements.forEach(($elem) => Form.showInvalid($elem, error.message));
            }
            else {
                Flash.add(`danger`, error.message);
            }
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
                        return $elem.val()
                    }
                })
                .filter(val => val !== null));
        }
    }

    static showInvalid($field, message) {
        let $parent;
        if($field.is(`[data-s2-initialized]`)) {
            $field = $field.parent().find(`.select2-selection`);
        } else if($field.is(`[type="file"]`)) {
            $field = $field.siblings('.btn');
        }

        if($field.is(`[data-wysiwyg]`)) {
            $parent = $field.parent();
        } else {
            $parent = $field.closest(`label, .wii-checkbox, .wii-radio-container`);
        }

        $field.addClass(`is-invalid`);
        $parent.find(`.invalid-feedback`).remove();
        $field = $field.is(`.select2-selection`)
            ? $field.closest(`.select2-container`).siblings(`select`)
            : $field;

        if($field.is(`[data-global-error]`)) {
            let label = $field.data(`global-error`) || $parent.find(`.field-label`).text();
            label = label
                .trim()
                .replace(/\*$/, '');
            const prefixMessage = label ? `${label} : ` : '';
            Flash.add(`danger`, `${prefixMessage}${message}`);
        } else {
            $parent.append(`<span class="invalid-feedback">${message}</span>`);
        }
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
            } else {
                $input = $form.find(`input[type="radio"][name="${input.name}"]`);
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
    } else if ($input.attr(`type`) === `tel`) {
        const regex = /^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})$/;
        if ($input.val() && !$input.val().match(regex)) {
            errors.push({
                elements: [$input],
                message: `Le numéro de téléphone n'est pas valide`,
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

    if ($input.is(`[required]`) || $input.is(`[data-required]`) || $input.is(`.needed`)) {
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
            const valueIsEmpty = (
                $input.is(`[data-wysiwyg]`) ? !$input.find(`.ql-editor`).text() :  // for wysuwyg fields
                ($input.is(`select[multiple]`) && Array.isArray($input.val())) ? $input.val().length === 0 : // for select2 multiple
                $input.is(`[type="file"]`) ? (!$input.val() && !$input.siblings('.preview-container').find('img').attr('src')) : // for input file
                !$input.val() // other fields
            );

            if (valueIsEmpty) {
                errors.push({
                    elements: [$input],
                    message: `Ce champ est requis`,
                });
            }
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

function processFiles($form, data) {
    $.each(droppedFiles, function(index, file) {
        data.set(`file${index}`, file);
    });

    const $savedFiles = $form.find('.data[name="savedFiles[]"]');
    if ($savedFiles.length > 0) {
        $savedFiles.each(function (index, field) {
            data.set(`files[${index}]`, $(field).val());
        });
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
            <img src="${$option.data('icon')??''}" width="20px" height="20px" class="round mr-2"/>
            ${state.text}
        </span>
    `);
}

