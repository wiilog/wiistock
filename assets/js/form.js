import WysiwygManager from "./wysiwyg-manager";
import Flash from "./flash";

export default class Form {

    submitCallback;
    processors = [];
    uploads = {};

    static create(selector) {
        const form = new Form();
        form.element = $(selector);

        WysiwygManager.initializeWYSIWYG(form.element);
        form.element.on(`click`, `[type="submit"]`, function() {
            const result = Form.process(form, {
                button: $(this),
            });

            if(result && form.submitCallback) {
                form.submitCallback(result);
            }
        });

        return form;
    }

    addProcessor(callback = null) {
        this.processors.push(callback);
        return this;
    }

    onSubmit(callback = null) {
        this.submitCallback = callback;
        return this;
    }

    static process(form, config = {}) {
        const classes = config.classes || {data: `data`, array: `data-array`};

        let $form;
        if(form instanceof Form)  {
            $form = form.element;
        } else {
            $form = form;
        }

        const errors = [];
        const data = new FormData();
        const $inputs = $form.find(`select.${classes.data}, input.${classes.data}, input[data-repeat], textarea.${classes.data}, .data[data-wysiwyg]`);

        //clear previous errors
        $form.find(`.is-invalid`).removeClass(`is-invalid`);
        $form.find(`.invalid-feedback`).remove();

        for(const input of $inputs) {
            let $input = $(input);

            if($input.is(`:not(.force-data, [type="hidden"]):hidden`) && !$input.closest(`.wii-switch, .wii-expanded-switch`).is(`:visible`) ||
                (config.ignored && ($input.is(config.ignored) || $input.closest(config.ignored).exists()))) {
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
            else if($input.attr(`type`) === `number`) {
                let val = parseInt($input.val());
                let min = parseInt($input.attr('min'));
                let max = parseInt($input.attr('max'));

                if(!isNaN(val) && (val > max || val < min)) {
                    let message = `La valeur `;
                    if(!isNaN(min) && !isNaN(max)) {
                        message += min > max
                            ? `doit être inférieure à ${max}.`
                            : `doit être comprise entre ${min} et ${max}.`;
                    } else if(!isNaN(max)) {
                        message += `doit être inférieure à ${max}.`;
                    } else if(!isNaN(min)) {
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
            else if($input.attr(`type`) === `tel`) {
                const regex = /^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})$/;
                if($input.val() && !$input.val().match(regex)) {
                    errors.push({
                        elements: [$input],
                        message: `Le numéro de téléphone n'est pas valide`,
                    });
                }
            }

            if($input.data(`repeat`)) {
                const $toRepeat = $form.find(`input[name="${$input.data(`repeat`)}"`);

                if($input.val() !== $toRepeat.val()) {
                    errors.push({
                        elements: [$input, $toRepeat],
                        message: `Les champs ne sont pas identiques`,
                    });
                }
            }

            if($input.is(`[required]`) || $input.is(`[data-required]`) || $input.is(`.needed`)) {
                if(([`radio`, `checkbox`].includes($input.attr(`type`)) && !$input.is(`:checked`))) {
                    errors.push({
                        elements: [$input.closest(`.wii-radio, .wii-checkbox, .wii-switch`)],
                        message: `Vous devez sélectionner au moins un élément`,
                    });
                } else if($input.is(`[data-wysiwyg]`) && !$input.find(`.ql-editor`).text() || !$input.is(`[data-wysiwyg]`) && !$input.val()) {
                    if(!$input.is(`[type="file"]`) || form instanceof Form && !form.uploads[$input.attr(`name`)]) {
                        errors.push({
                            elements: [$input],
                            message: `Ce champ est requis`,
                        });
                    }
                }
            }

            if($input.attr(`name`) || $input.attr(`data-wysiwyg`)) {
                let value;
                if($input.is(`[data-wysiwyg]`)) {
                    const $qlEditor = $input.find(`.ql-editor`);
                    const $wrapper = $qlEditor.exists() ? $qlEditor : $input;
                    value = $wrapper.html();
                } else if($input.attr(`type`) === `checkbox`) {
                    value = $input.is(`:checked`) ? `1` : `0`;
                } else if($input.attr(`type`) === `file`) {
                    value = $input[0].files[0] ?? null;
                } else {
                    value = $input.val() || null;
                }

                if(typeof value === `string`) {
                    value = value.trim();
                }

                if(value !== null || $input.is('[data-nullable]')) {
                    const $multipleKey = $input.closest(`[data-multiple-key]`);
                    if($multipleKey.exists()) {
                        const multipleKey = JSON.parse(data.get($multipleKey.data(`multiple-key`)) || `{}`);
                        if(!multipleKey[$multipleKey.data(`multiple-object-index`)]) {
                            multipleKey[$multipleKey.data(`multiple-object-index`)] = {};
                        }

                        const multipleObject = multipleKey[$multipleKey.data(`multiple-object-index`)];
                        multipleObject[$input.attr(`name`) || $input.attr(`data-wysiwyg`)] = value;
                        data.set($multipleKey.data(`multiple-key`), JSON.stringify(multipleKey));
                    } else {
                        data.set($input.attr(`name`) || $input.attr(`data-wysiwyg`), value);
                    }
                }
            }
        }

        Form.addDataArray($form, data, classes);

        if(config.button && config.button.attr(`name`)) {
            data.append(config.button.attr(`name`), config.button.val());
        }

        if(form instanceof Form) {
            for(const processor of form.processors) {
                processor(data, errors, $form);
            }
        }

        if(config.ignoreErrors) {
            return data;
        }

        // display errors under each field
        for(const error of errors) {
            error.elements.forEach($elem => Form.showInvalid($elem, error.message));
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
            $field = $field.parent();
        }

        if($field.is(`[data-wysiwyg]`)) {
            $parent = $field.parent();
        } else {
            $parent = $field.closest(`label, .wii-checkbox, .wii-radio-container`);
        }

        $field.addClass(`is-invalid`);
        $parent.find(`.invalid-feedback`).remove();
        if($field.is(`[data-global-error]`)) {
            const label = $field.data(`global-error`) || $parent.find(`.field-label`).text();
            Flash.add(`danger`, `${label} : ${message}`);
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
