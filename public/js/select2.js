
class Select2Old {

    /**
     * @param $select
     * @param {{}|{route: string, param: {}|undefined, success?: function(result, term)}} ajaxOptions
     * @param lengthMin
     * @param placeholder
     * @param {boolean|undefined} autoSelect
     * @param {*} $nextField
     * @param {string|undefined} defaultOptionText
     * @param {string|undefined} defaultOptionValue
     */
    static init($select,
                placeholder = '',
                lengthMin = 0,
                ajaxOptions = {},
                {autoSelect, $nextField} = {},
                {value: defaultOptionValue, text: defaultOptionText} = {}) {

        $select.each(function () {
            const $self = $(this);
            let isMultiple = $self.attr('multiple') === 'multiple';

            if (defaultOptionValue && defaultOptionText) {
                const $existingDefaultOption = $self.find(`option[value="${defaultOptionValue}"]`);
                if ($existingDefaultOption
                    && $existingDefaultOption.length > 0) {
                    $existingDefaultOption.prop('selected', true);
                } else {
                    let newOption = new Option(defaultOptionText, defaultOptionValue, true, true);
                    $self.append(newOption).trigger('change');
                }
            }

            const select2AjaxOptions = ajaxOptions && ajaxOptions.route
                ? {
                    ajax: {
                        url: Routing.generate(ajaxOptions.route, ajaxOptions.param || {}, true),
                        dataType: 'json',
                        delay: 250,
                        ...(
                            autoSelect
                                ? {
                                    processResults: (data, {term}) => {
                                        const {results = []} = (data || {});

                                        if (results
                                            && results.length > 0
                                            && results[0].text === term) {

                                            const option = new Option(results[0].text, results[0].id, true, true);
                                            const oldVal = $self.val();
                                            const newVal = (
                                                !isMultiple
                                                    ? option
                                                    : [
                                                        ...(oldVal || []),
                                                        option
                                                    ]
                                            );

                                            setTimeout(() => {
                                                $self
                                                    .append(newVal)
                                                    .trigger('change');

                                                $self.select2('close');
                                                if ($nextField) {

                                                    if ($nextField.data('select2')) {
                                                        $nextField.select2('open');
                                                    } else {
                                                        $nextField.trigger('focus');
                                                    }
                                                }
                                            });
                                        }
                                        return data;
                                    }
                                }
                                : {}
                        )
                    }
                }
                : {};

            const getSelect2Selection = () => (
                $self
                    .siblings('.select2-container')
                    .find('.select2-selection')
            );

            let $select2Selection = getSelect2Selection();

            if ($select2Selection.length > 0) {
                $select2Selection.off('focus');
            }
            $self.select2({
                ...select2AjaxOptions,
                language: {
                    inputTooShort: function () {
                        let s = lengthMin > 1 ? 's' : '';
                        return Translation.of(`Général`, '', 'Zone filtre', 'Veuillez entrer au moins {1} caractère{2}.',{1: lengthMin, 2: s}, false);
                    },
                    searching: function () {
                        return Translation.of(`Général`, '', 'Zone filtre', 'Recherche en cours...', false);
                    },
                    noResults: function () {
                        return Translation.of(`Général`, '', 'Zone filtre', 'Aucun résultat.', false);
                    }
                },
                minimumInputLength: lengthMin,
                placeholder: {
                    id: 0,
                    text: placeholder,
                },
                allowClear: !isMultiple
            });

            // on recupère le select2 après l'initialisation de select2
            $select2Selection = getSelect2Selection();
            $select2Selection.on('focus', function () {
                if (!isMultiple) {
                    $self.select2('open');
                }
            });
        });
    }

    static location(select, autoSelectOptions, placeholder = '', lengthMin = 1) {
        this.init(select, placeholder, lengthMin, {route: 'get_emplacement'}, autoSelectOptions);
    }

    static carrier(select) {
        this.init(select, '', 1, {route: 'get_transporteurs'});
    }

    static articleReference(select, {placeholder = null, typeQuantity = null, field = `reference`, activeOnly = 1, minQuantity = null, locationFilter = null, buyerFilter = null } = {}) {
        this.init(select, placeholder, 1, {
            route: 'get_ref_articles',
            param: {
                activeOnly,
                minQuantity,
                field,
                ...(typeQuantity ? {typeQuantity} : {}),
                locationFilter,
                buyerFilter
            }
        });
    }

    static article(select, referenceArticleReference = null, lengthMin = 1) {
        this.init(select, '', lengthMin, {route: 'get_articles', param: {activeOnly: 1, referenceArticleReference, activeReferenceOnly: 1}});
    }

    static articleReception(select, receptionId = null) {
        let reception = receptionId ? receptionId : $('#receptionId').val();
        this.init(select, '', 1, {route: 'get_article_reception', param: {reception: reception}});
    }

    static provider(select, placeholder = '', route = 'ajax_select_provider') {
        this.init(select, placeholder, 1, { route });
    }

    static frequency(select) {
        this.init(select, '', 1, {route: 'get_frequencies'});
    }

    static driver(select) {
        this.init(select, '', 1, {route: 'get_chauffeur'});
    }

    static user($select = null, placeholder = '', lengthMin = 1) {
        if(typeof $select === "string") {
            placeholder = $select;
            $select = $('.ajax-autocomplete-user');
        } else if($select == null) {
            $select = $('.ajax-autocomplete-user');
        }

        this.init($select, placeholder, lengthMin, {route: 'ajax_select_user'});
    }

    static dispatch(select, placeholder = '') {
        this.init(select, placeholder, 1, {route: 'get_dispatch_numbers'});
    }

    static dispute(select, placeholder = '') {
        this.init(select, placeholder, 1, {route: 'get_dispute_number'});
    }

    static collect(select) {
        this.init(select, 'Numéros de demande', 3, {route: 'get_demand_collect'});
    }

    static demand(select) {
        this.init(select, 'Numéros de demande', 3, {route: 'get_demandes'});
    }

    static initFree($selects, placeholder = undefined) {
        $selects.each(function () {
            const $self = $(this);
            $self.select2({
                tags: true,
                ...(placeholder
                    ? {
                        placeholder: {
                            id: 0,
                            text: placeholder,
                        }
                    }
                    : {}),
                "language": {
                    "noResults": function () {
                        return Translation.of(`Général`, '', 'Zone filtre', 'Ajouter des éléments', false);
                    }
                },
            });
            $self.next('.select2-container').find('.select2-selection').on('focus', () => {
                $(this).closest(".select2-container").siblings('select:enabled').select2('open');
            });
        });
    }

    static initValues($select, $inputValue, forceInit = false) {
        const data = $inputValue.data();
        if (data && data.id && data.text) {
            let idArr = data.id.toString().split(',');
            let textArr = data.text.toString().split(',');

            for (let i = 0; i < idArr.length; i++) {
                let option = new Option(textArr[i], idArr[i], true, true);
                $select.append(option).trigger('change');
            }
        } else if (forceInit) {
            $select.val(null).trigger('change');
        }
    }

    static open($select2) {
        $select2.select2('open');
    }

}
