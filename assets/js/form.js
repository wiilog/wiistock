export default class Form {

    static QUILL_CONFIG = {
        modules: {
            toolbar: [
                [{header: [1, 2, 3, false]}],
                ['bold', 'italic', 'underline', 'image'],
                [{'list': 'ordered'}, {'list': 'bullet'}]
            ]
        },
        formats: [
            'header',
            'bold', 'italic', 'underline', 'strike', 'blockquote',
            'list', 'bullet', 'indent', 'link', 'image'
        ],
        theme: 'snow'
    };

    submitCallback;
    processors = [];
    uploads = {};

    static create(selector) {
        const form = new Form();
        form.element = $(selector);

        form.initializeWYSIWYG();
        form.element.on(`click`, `[type="submit"]`, function() {
            const result = form.process($(this));
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

    initializeWYSIWYG() {
        const initializer = function() {
            new Quill(this, Form.QUILL_CONFIG)
        };

        this.element.find(`[data-wysiwyg]`).each(initializer);
        this.element.arrive(`[data-wysiwyg]`, initializer);
    }

    process($button = null, classes = {data: `data`, array: `data-array`}) {
        const errors = [];
        const data = new FormData();
        const $inputs = this.element.find(`select.${classes.data}, input.${classes.data}, input[data-repeat], textarea.${classes.data}, .data[data-wysiwyg]`);

        //clear previous errors
        this.element.find(`.is-invalid`).removeClass(`is-invalid`);
        this.element.find(`.invalid-feedback`).remove();

        for(const input of $inputs) {
            let $input = $(input);
            if($input.is(`:not([type="hidden"]):hidden`)) {
                continue;
            }

            if($input.attr(`type`) === `radio`) {
                const $checked = this.element.find(`input[type="radio"][name="${input.name}"]:checked`);
                if($checked.exists()) {
                    $input = $checked;
                } else {
                    $input = this.element.find(`input[type="radio"][name="${input.name}"]`);
                }
            } else if($input.attr(`type`) === `number`) {
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
            } else if($input.attr(`type`) === `tel`) {
                const regex = /^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:(?:[\s.-]?\d{2}){4}|\d{2}(?:[\s.-]?\d{3}){2})$/;
                if($input.val() && !$input.val().match(regex)) {
                    errors.push({
                        elements: [$input],
                        message: `Le numéro de téléphone n'est pas valide`,
                    });
                }
            }

            if($input.data(`repeat`)) {
                const $toRepeat = this.element.find(`input[name="${$input.data(`repeat`)}"`);

                if($input.val() !== $toRepeat.val()) {
                    errors.push({
                        elements: [$input, $toRepeat],
                        message: `Les champs ne sont pas identiques`,
                    });
                }
            }

            if($input.is(`[required]`) || $input.is(`[data-required]`)) {
                if(([`radio`, `checkbox`].includes($input.attr(`type`)) && !$input.is(`:checked`))) {
                    errors.push({
                        elements: [$input.closest(`.wii-radio, .wii-checkbox`)],
                        message: `Vous devez sélectionner au moins un élément`,
                    });
                } else if($input.is(`[data-wysiwyg]`) && !$input.find(`.ql-editor`).text() || !$input.is(`[data-wysiwyg]`) && !$input.val()) {
                    if(!$input.is(`[type="file"]`) || !this.uploads[$input.attr(`name`)]) {
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
                    value = $input.find(`.ql-editor`).html();
                } else if($input.attr(`type`) === `checkbox`) {
                    value = $input.is(`:checked`) ? `1` : `0`;
                } else {
                    value = $input.val() || null;
                }

                if(typeof value === `string`) {
                    value = value.trim();
                }

                if(value !== null) {
                    const $multipleKey = $input.closest(`[data-multiple-key]`);
                    if($multipleKey.exists()) {
                        const multipleKey = JSON.parse(data.get($multipleKey.data(`multiple-key`)) || `{}`);
                        if(!multipleKey[$multipleKey.data(`multiple-object-index`)]) {
                            multipleKey[$multipleKey.data(`multiple-object-index`)] = {};
                        }

                        const multipleObject = multipleKey[$multipleKey.data(`multiple-object-index`)];
                        multipleObject[$input.attr(`name`) || $input.attr(`data-wysiwyg`)] = value;

                        data.append($multipleKey.data(`multiple-key`), JSON.stringify(multipleKey));
                    } else {
                        data.append($input.attr(`name`) || $input.attr(`data-wysiwyg`), value);
                    }
                }
            }
        }

        this.addDataArray(data, classes);

        if($button && $button.attr(`name`)) {
            data.append($button.attr(`name`), $button.val());
        }

        // add uploads
        for(const [name, file] of Object.entries(this.uploads)) {
            data.append(name, file)
        }

        for(const processor of this.processors) {
            processor(data, errors, this.element);
        }

        // display errors under each field
        this.element.find(`.global-error`).remove();
        for(const error of errors) {
            error.elements.forEach($elem => this.showInvalid($elem, error.message));
        }

        return errors.length === 0 ? data : false;
    }

    addDataArray(data, classes) {
        const $arrays = this.element.find(`select.${classes.array}, input.${classes.array}`);
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

    showInvalid($field, message) {
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
            showBSAlert(`${$parent.find(`.field-label`).text()} : ${message}`, `danger`);
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

FormData.fromObject = function(object) {
    const data = new FormData();
    for(const [key, value] of Object.entries(object)) {
        data.append(key, value);
    }

    return data;
}
