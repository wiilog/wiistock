
class Select2 {

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

                                                    if (!$nextField.data('select2')) {
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
                        return 'Veuillez entrer au moins ' + lengthMin + ' caractère' + s + '.';
                    },
                    searching: function () {
                        return 'Recherche en cours...';
                    },
                    noResults: function () {
                        return 'Aucun résultat.';
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

    static articleReference(select, typeQuantity = null, field = 'reference', placeholder = '', activeOnly = 1) {
        this.init(select, placeholder, 1, {
            route: 'get_ref_articles',
            param: {
                activeOnly,
                field,
                typeQuantity
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

    static provider(select, placeholder = '', route = 'get_fournisseur') {
        this.init(select, placeholder, 1, { route });
    }

    static frequency(select) {
        this.init(select, '', 1, {route: 'get_frequencies'});
    }

    static driver(select) {
        this.init(select, '', 1, {route: 'get_chauffeur'});
    }

    static user($select = null, placeholder = '') {
        if(typeof $select === "string") {
            placeholder = $select;
            $select = $('.ajax-autocomplete-user');
        } else if($select == null) {
            $select = $('.ajax-autocomplete-user');
        }

        this.init($select, placeholder, 1, {route: 'get_user'});
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

}
