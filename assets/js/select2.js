import 'select2';
import {GROUP_EVERYTHING, GROUP_WHEN_NEEDED} from "./app";

const ROUTES = {
    handlingType: `ajax_select_handling_type`,
    deliveryType: `ajax_select_delivery_type`,
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
}

const INSTANT_SELECT_TYPES = {
    handlingType: true,
    deliveryType: true,
    collectType: true,
    dispatchType: true,
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
    static init($element) {
        setTimeout(() => {
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

                $element.attr(`data-s2-initialized`, ``);
                $element.removeAttr(`data-s2`);

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
                            const $search = $element.parent().find(`.select2-search__field`);

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
                                    $element.attr("data-length", data.availableResults);
                                }

                                return data;
                            }
                        }
                    };

                    if ($element.is(`[data-min-length]`) || !INSTANT_SELECT_TYPES[type]) {
                        const minLength = $element.data('min-length');
                        config.minimumInputLength = minLength !== undefined ? minLength : 1;
                    }
                }
                const allowClear = !($element.is(`[multiple]`) || $element.is(`[data-no-empty-option]`));
                const editable = $element.is('[data-editable]');

                if($element.is(`[data-keep-open]`)){
                    config.closeOnSelect = false;
                }

                $element.select2({
                    placeholder: $element.data(`placeholder`) || '',
                    tags: editable,
                    allowClear,
                    dropdownParent,
                    language: {
                        inputTooShort: () => Translation.of(`Général`, '', 'Zone filtre', 'Veuillez entrer au moins {1} caractère{2}.', {1: '1', 2: ''}, false),
                        noResults: () => Translation.of(`Général`, '', 'Zone filtre', 'Aucun résultat.', false),
                        searching: () => Translation.of(`Général`, '', 'Zone filtre', 'Recherche en cours...', false),
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
                    if ($element.val() === `new-item` && search && search.length) {
                        $element.append(new Option(search, search, true, true)).trigger('change');
                    }
                })

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

                    const $searchField = $('.select2-dropdown .select2-search__field');
                    if ($searchField.exists()) {
                        setTimeout(() => $searchField[0].focus(), 300);
                    }

                    search = null;
                    $(document)
                        .off(`keyup.select2-save-search`)
                        .on(`keyup.select2-save-search`, `.select2-dropdown .select2-search__field`, () => {
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
        });
    }

    static includeParams($element, params) {
        if ($element.is('[data-other-params]')) {
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
        }

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

            const values = $fields
                .filter((_, elem) => elem.name && elem.value)
                .keymap((elem) => [elem.name, elem.value], needGroup ? GROUP_EVERYTHING : GROUP_WHEN_NEEDED);

            params = {
                ...params,
                ...values,
            };
        }

        return params;
    }

    static destroy($element) {
        if($element.is(`.select2-hidden-accessible`)) {
            $element.val(null).html(``);
            $element.select2(`data`, null);
            $element.select2(`destroy`);
        }
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => {
    Select2.init($(elem))
}));

$(document).arrive(`[data-s2]`, function() {
    Select2.init($(this));
});
