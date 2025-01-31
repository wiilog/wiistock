import 'select2';
import {GROUP_EVERYTHING, GROUP_WHEN_NEEDED} from "@app/app";
import Routing from '@app/fos-routing';

const ROUTES = {
    handlingType: `ajax_select_handling_type`,
    collectType: `ajax_select_collect_type`,
    referenceType: `ajax_select_reference_type`,
    dispatchType: `ajax_select_dispatch_type`,
    status: `ajax_select_status`,
    location: `ajax_select_locations`,
    roundsDelivererPending: `ajax_select_rounds_deliverer_pending`,
    pack: `ajax_select_packs`,
    nature: `ajax_select_natures`,
    sensor: `ajax_select_sensors`,
    sensorWrapper: `ajax_select_sensor_wrappers`,
    sensorWrapperForPairings: `ajax_select_sensor_wrappers_for_pairings`,
    reference: `ajax_select_references`,
    packWithoutPairing: `ajax_select_packs_without_pairing`,
    articleWithoutPairing: `ajax_select_articles_without_pairing`,
    locationWithoutPairing: `ajax_select_locations_without_pairing`,
    sensorWithoutPairing: `ajax_select_sensors_without_pairing`,
    sensorCodeWithoutPairing: `ajax_select_sensors_code_without_pairing`,
    triggerSensorWithoutPairing: `ajax_select_trigger_sensors_without_pairing`,
    triggerSensorCodeWithoutPairing: `ajax_select_trigger_sensors_code_without_pairing`,
    visibilityGroup: `ajax_select_visibility_group`,
    user: `ajax_select_user`,
    roles: `ajax_select_roles`,
    supplierCode: `ajax_select_supplier_code`,
    supplierLabel: `ajax_select_supplier_label`,
    collectableArticles: `ajax_select_collectable_articles`,
    purchaseRequest: `ajax_select_references_by_buyer`,
    keyboardPacks: `ajax_select_keyboard_pack`,
    businessUnit: `ajax_select_business_unit`,
    article: `ajax_select_articles`,
    availableArticle: `ajax_select_available_articles`,
    carrier: 'ajax_select_carrier',
    types: 'ajax_select_types',
    vehicles: 'ajax_select_vehicles',
    inventoryCategories: 'ajax_select_inventory_categories',
    project: 'ajax_select_project',
    receptionLogisticUnits: 'ajax_select_reception_logistic_units',
    deliveryLogisticUnits: 'ajax_select_delivery_logistic_units',
    customers: 'ajax_select_customers',
    natureOrTypeSelect: 'ajax_select_nature_or_type',
    dispatchPacks: 'ajax_select_dispatch_packs',
    zones: 'ajax_select_zones',
    provider: 'ajax_select_provider',
    nativeCountries: 'ajax_select_native_countries',
    supplierArticles: 'ajax_select_supplier_articles',
    driver: 'ajax_select_driver',
    truckArrivalLine: 'ajax_select_truck_arrival_line',
    truckArrival: 'ajax_select_truck_arrival',
    locationWithGroups: `ajax_select_location_with_group`,
    productionRequestType: `ajax_select_production_request_type`,
    freeField: `ajax_select_free_field`,
}

const INSTANT_SELECT_TYPES = {
    handlingType: true,
    deliveryType: true,
    collectType: true,
    dispatchType: true,
    productionRequestType: true,
    status: true,
    sensorWithoutPairing: true,
    sensorCodeWithoutPairing: true,
    triggerSensorWithoutPairing: true,
    triggerSensorCodeWithoutPairing: true,
    businessUnit: true,
    roundsDelivererPending: true,
    inventoryCategories: true,
}

export default class Select2 {
    static proceed($element, direct = false) {
        // setTimeout exécute le callback à la fin de la pile d'exécution Javascript. Nous avons besoin que le select2 soit initialisé avant dans certains cas.
        if(!direct) {
            setTimeout(() => this.init($element));
        } else {
            this.init($element);
        }
    }

    static init($element) {
        const type = $element.data(`s2`);

        let search = null;

        const dropdownParent = $element.is(`[data-parent]`) ? $($element.data(`parent`)) : $element.parent();
        if ($element.is(`[data-simple]`)) {
            $element.select2({dropdownParent})
        } else {
            if (!$element.find(`option[selected]`).exists()
                && !type
                && !$element.is(`[data-no-empty-option]`)
                && !$element.is(`[data-editable]`)
                && !$element.is(`[multiple]`)) {
                $element.prepend(`<option selected>`);
            }

            $element
                .attr(`data-s2-initialized`, type || '')
                .data(`s2-initialized`, type || '');
            $element
                .removeAttr(`data-s2`)
                .removeData(`s2`);

            const config = {};
            if (type) {
                if (!ROUTES[type]) {
                    console.error(`No select route found for ${type}`);
                }
                config.ajax = {
                    url: Routing.generate(ROUTES[type]),
                    dataType: `json`,
                    data: params => Select2.includeParams($element, params),
                    processResults: (data) => {
                        return processResult($element, data);
                    }
                };

                if ($element.is(`[data-min-length]`) || !INSTANT_SELECT_TYPES[type]) {
                    const minLength = $element.data('min-length');
                    config.minimumInputLength = minLength !== undefined ? minLength : 1;
                }
            }
            let tokenizer;
            const allowClear = !($element.is(`[multiple]`) || $element.is(`[data-no-empty-option]`));
            const editable = $element.is('[data-editable]');
            if (editable) {
                const separator = $element.data('editable-token-separator');

                if (separator) {
                    tokenizer = (input, selection, callback) => {
                        return Select2.tokenizer(input, selection, callback, separator);
                    }
                }
            }

            if($element.is(`[data-keep-open]`)){
                config.closeOnSelect = false;
            }

            if ($element.is(`[data-max-selection-length]`)) {
                config.maximumSelectionLength = Number($element.data('max-selection-length'));
            }

            $element.select2({
                placeholder: $element.data(`placeholder`) || '',
                tags: editable,
                ...(tokenizer ? {tokenizer} : {}),
                allowClear,
                dropdownParent,
                language: {
                    inputTooShort: () => Translation.of(`Général`, '', 'Zone filtre', 'Veuillez entrer au moins {1} caractère{2}.', {1: '1', 2: ''}, false),
                    noResults: () => Translation.of(`Général`, '', 'Zone filtre', 'Aucun résultat.', false),
                    searching: () => Translation.of(`Général`, '', 'Zone filtre', 'Recherche en cours...', false),
                    maximumSelected: ({maximum}) => `Vous ne pouvez sélectionner que ${maximum} éléments.`,
                },
                escapeMarkup: markup => markup,
                templateResult: (data, container) => {
                    if (data.highlighted) {
                        $(container).attr(`data-highlighted`, true);
                    }
                    return data.html || data.text;
                },
                templateSelection: function (data, container) {
                    return data.html || data.text || undefined;
                },
                ...config,
            });

            $element.on(`change`, () => {
                const [selected] = $element.select2('data').reverse();
                if (selected) {
                    if (selected.id === `new-item` && search && search.length) {
                        $element.append(
                            $(new Option(search, search, true, true))
                        )
                        $element
                            .find('option[value="new-item"]')
                            .remove();
                        $element.trigger('change');
                        const newItem = $element.select2('data').find(({id}) => id === search);
                        newItem.isNewElement = true;
                    } else if (selected.id === `redirect-url` && selected.url) {
                        location.href = selected.url;
                        $element
                            .val(null)
                            .trigger('change');
                    }
                }
            });

            if ($element.is('[data-no-full-size]')) {
                $element.siblings('.select2-container')
                    .addClass('no-full-size');
            }

            if ($element.is(`[data-keep-selected-order]`)) {
                $element.on(`select2:select`, function (event) {
                    const $option = $(event.params.data.element);

                    $option.detach();
                    $(this)
                        .append($option)
                        .trigger(`change`);
                });
            }

            $(document).arrive(`.select2-dropdown [data-highlighted]`, function () {
                const $highlighted = $(this);
                const $results = $highlighted.closest('.select2-results__options');

                $results.find(`.select2-results__option--highlighted`).removeClass(`select2-results__option--highlighted`);
                $highlighted.addClass("select2-results__option--highlighted");
            })

            if(editable) {
                $element.on(`select2:unselecting`, event => {
                    const $option = $(event.params.args.data.element);
                    if ($option.hasClass('no-deletable')) {
                        event.preventDefault();
                        Flash.add(`danger`, `Cet élément est utilisé, vous ne pouvez pas le supprimer.`);
                    } else {
                        $option.remove();
                    }

                    if($element.is(`[data-default-values]`)) {
                        const defaultValues = $element.data(`default-values`).split(`,`);
                        const $option = $(event.params.args.data.element);

                        if(defaultValues.includes($option.val())) {
                            $element.append($option);
                        }
                    }
                });
            }

            $element.on('select2:open', function (e) {
                const evt = "scroll.select2";
                $(e.target).parents().off(evt);
                $(window).off(evt);
                // we hide all other select2 dropdown
                $('[data-s2-initialized]').each(function () {
                    const $select2 = $(this);
                    if (!$select2.is($element)) {
                        $select2.select2('close');
                    }
                });

                const $focusShadowParent = $element.closest(`.focus-shadow`);
                if ($focusShadowParent.exists()) {
                    $element.closest(`tr`).addClass(`focus-within`);
                }

                let $searchField = $(e.target).prop('multiple')
                    ? $(e.target).siblings('.select2-container').find('.select2-selection--multiple .select2-search--inline .select2-search__field')
                    : $('.select2-dropdown .select2-search__field');
                if ($searchField.exists()) {
                    setTimeout(() => $searchField[0].focus(), 300);
                }

                search = null;
                $(document)
                    .off(`keyup.select2-save-search`)
                    .on(`keyup.select2-save-search`, `.select2-dropdown .select2-search__field, .select2-search--inline .select2-search__field`, () => {
                        search = $searchField.val();
                    });

                if ($element.is(`[data-no-search]`)) {
                    $element.siblings('.select2-container')
                        .find('.select2-dropdown .select2-search')
                        .addClass('d-none');
                }

                if ($element.is('[data-hidden-dropdown]')) {
                    $element.siblings('.select2-container')
                        .addClass('hidden-dropdown') ;
                }

                if ($element.is('[data-disabled-dropdown-options]')) {
                    $element.siblings('.select2-container')
                        .addClass('disabled-dropdown-options') ;
                }
            });

            $element.on('select2:close', function (e) {
                $element.closest(`tr`).removeClass(`focus-within`);
            });

            if ($element.is(`[autofocus]:visible`)) {
                $element.select2(`open`);
            }

            if ($element.is('[data-search-prefix]')) {
                const searchPrefixDisplayed = ($element.data('search-prefix-displayed') || $element.data('search-prefix'));
                const {$dropdown} = $element.data('select2');
                const $prefixContainer = $dropdown.find('.select2-search');
                $prefixContainer.addClass(`d-flex`);
                $prefixContainer.prepend(`
                        <input class="search-prefix" name="searchPrefix" size=${searchPrefixDisplayed.length} value="${searchPrefixDisplayed}" disabled/>
                    `);
            }
        }
    }

    static includeParams($element, params) {
        //check for other params
        const attributes = $element.attr();
        const otherParams = Object.keys(attributes)
            .reduce((carry, key) => {
                const [_, keyWithoutPrefix] = key.match(/other-params-(.+)/) || [];
                if (keyWithoutPrefix) {
                    carry[keyWithoutPrefix] = attributes[key];
                }
                return carry;
            }, {});

        params = {
            ...params,
            ...otherParams,
        };

        if ($element.is('[data-search-prefix]')) {
            const searchPrefix = $element.data('search-prefix');
            params = {
                ...params,
                searchPrefix,
            };
        }

        if($element.is(`[data-include-params]`)) {
            const selector = $element.data(`include-params`);
            const needGroup = $element.is(`[data-include-params-group]`);
            const closest = $element.data(`include-params-parent`) || `.modal, .wii-form`;
            const $fields = $element
                .closest(closest)
                .find(selector);

            const getName = (elem) => $(elem).data('include-params-name') || elem.name;

            const values = $fields
                .filter((_, elem) => getName(elem) && elem.value)
                .keymap((elem) => [getName(elem), elem.value], needGroup ? GROUP_EVERYTHING : GROUP_WHEN_NEEDED);

            params = {
                ...params,
                ...values,
            };
        }

        return params;
    }

    static destroy($element) {
        if ($element.is(`.select2-hidden-accessible`)) {
            $element.val(null).html(``);
            $element.select2(`data`, null);
            $element.select2(`destroy`);

            if ($element.is(`[data-s2-initialized]`)) {
                const type = $element.data(`s2-initialized`);
                $element
                    .attr(`data-s2`, type)
                    .data(`s2`, type)
                    .removeAttr(`data-s2-initialized`)
                    .removeData(`s2-initialized`);
            }
        }
    }

    static reload($element) {
        Select2.destroy($element);
        Select2.proceed($element, true);
    }

    static tokenizer(input, selection, callback, delimiter) {
        let term = input.term;
        if (term.indexOf(delimiter) < 0) {
            return input;
        }

        const parts = term.split(delimiter);
        for (const part of parts) {
            const trim = part.trim();
            if (trim) {
                callback({
                    id: trim,
                    text: trim
                });
            }
        }

        return { term: parts.join(delimiter) }; // Rejoin unmatched tokens
    }

    static initSelectMultipleWarning($element, $warningMessage, check, options = {}) {
        $element.off('change.Check').on('change.Check', function () {
            let $options = $(this).find('option:selected')
            $warningMessage.prop('hidden', true);

            // Wait for select2 to render the options
            setTimeout(function () {
                $options.each(async function () {
                    let $option = $(this);
                    let value = $option.val();

                    if (await check($option)) {
                        $option.removeClass('invalid');
                    } else {
                        $options.closest('label').find('.select2-container ul.select2-selection__rendered li.select2-selection__choice[title="' + value + '"]').addClass('warning');
                        $warningMessage.prop('hidden', false);
                        options.onWarning && options.onWarning();
                    }
                });
            }, 10);
        })
    }
}

$(() => {
    $(`[data-s2]`).each((id, elem) => {
        Select2.proceed($(elem))
    });
});

$(document).arrive(`[data-s2]`, function() {
    Select2.proceed($(this));
});


function processResult($element, data) {
    const select2Element = $element.data("select2");
    const $dropdown = select2Element.$dropdown;

    const $searchInContainer = select2Element.$container.find('.select2-search__field');
    const $searchInDropdown = $dropdown.find('.select2-search__field');
    const $search = $searchInContainer.exists() ? $searchInContainer : $searchInDropdown;

    if (data.error) {
        $search.addClass(`is-invalid`);

        return {
            results: [{
                id: `error`,
                html: `<span class="text-danger">${data.error}</span>`,
                disabled: true,
            }],
        };
    } else {
        $search.removeClass(`is-invalid`);

        if(data.availableResults) {
            $element
                .data('length', data.availableResults)
                .attr("data-length", data.availableResults);
        }

        const searchValue = $search.val();
        if ($element.is(`[data-auto-select]`)
            && searchValue) {
            const resultWithoutNewItem = (data.results || []).filter(({id}) => id !== "new-item");

            if (resultWithoutNewItem.length === 1
                && resultWithoutNewItem[0].text === searchValue) {
                setTimeout(() => {
                    // prevent duplicates
                    const selectedData = $element.select2('data');
                    const alreadySelected = selectedData.findIndex(({id}) => id === searchValue) > -1;
                    if (!alreadySelected) {
                        const [option] = $dropdown.find('.select2-results__option')
                            .toArray()
                            .filter((element) => !$(element).find('.new-item-container').exists());
                        $(option).trigger("mouseup");
                        $element.trigger({
                            type: "select2:select",
                            params: {
                                data: resultWithoutNewItem[0]
                            }
                        });
                    }
                }, 50);
            }
        }
        return data;
    }
}
