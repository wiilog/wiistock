import 'select2';
import {GROUP_WHEN_NEEDED} from "./app";

const ROUTES = {
    handlingType: `ajax_select_handling_type`,
    deliveryType: `ajax_select_delivery_type`,
    collectType: `ajax_select_collect_type`,
    status: `ajax_select_status`,
    location: `ajax_select_locations`,
    pack: `ajax_select_packs`,
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
    supplierCode: `ajax_select_supplier_code`,
    supplierLabel: `ajax_select_supplier_label`,
    collectableArticles: `ajax_select_collectable_articles`,
    purchaseRequest: `ajax_select_references_by_buyer`,
    keyboardPacks: `ajax_select_keyboard_pack`,
}

const INSTANT_SELECT_TYPES = {
    handlingType: true,
    deliveryType: true,
    collectType: true,
    status: true,
    sensorWithoutPairing: true,
    sensorCodeWithoutPairing: true,
    triggerSensorWithoutPairing: true,
    triggerSensorCodeWithoutPairing: true,
}

export default class Select2 {
    static init($element) {
        const type = $element.data(`s2`);
        let search = null;

        if(!$element.find(`option[selected]`).exists()
            && !type
            && !$element.is(`[data-no-empty-option]`)
            && !$element.is(`[data-editable]`)) {
            $element.prepend(`<option selected>`);
        }

        $element.attr(`data-s2-initialized`, ``);
        $element.removeAttr(`data-s2`);

        const config = {};
        if(type) {
            if(!ROUTES[type]) {
                console.error(`No select route found for ${type}`);
            }

            config.ajax = {
                url: Routing.generate(ROUTES[type]),
                dataType: `json`,
                data: params => Select2.includeParams($element, params),
                processResults: (data, params) => {
                    const $search = $element.parent().find(`.select2-search__field`);

                    if(data.error) {
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
                        return data;
                    }
                }
            };
        }

        if (type && !INSTANT_SELECT_TYPES[type]) {
            config.minimumInputLength = 1;
        }

        $element.select2({
            placeholder: $element.data(`placeholder`),
            tags: $element.is('[data-editable]'),
            allowClear: !$element.is(`[multiple]`),
            dropdownParent: $element.is(`[data-parent]`) ? $($element.data(`parent`)) : $element.parent(),
            language: {
                inputTooShort: () => 'Veuillez entrer au moins 1 caractère.',
                noResults: () => `Aucun résultat`,
                searching: () => null,
            },
            escapeMarkup: markup => markup,
            templateResult: (data, container) => {
                if(data.highlighted) {
                    $(container).attr(`data-highlighted`, true);
                }

                return data.html || data.text;
            },
            templateSelection: function (data, container) {
                return data.html || data.text;
            },
            ...config,
        });

        $element.on(`change`, () => {
            if($element.val() === `new-item` && search && search.length) {
                $element.append(new Option(search, search, true, true)).trigger('change');
            }
        })

        $(document).arrive(`.select2-dropdown [data-highlighted]`, function() {
            const $highlighted = $(this);
            const $results = $highlighted.closest('.select2-results__options');

            $results.find(`.select2-results__option--highlighted`).removeClass(`select2-results__option--highlighted`);
            $highlighted.addClass("select2-results__option--highlighted");
        })

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

            const $searchField = $('.select2-dropdown .select2-search__field');
            console.log($searchField);
            if ($searchField.exists()) {
                setTimeout(() => $searchField[0].focus(), 300);
            }

            search = null;
            $(document).on(`keyup`, `.select2-dropdown .select2-search__field`, () => {
                console.log("huh");
                console.error(search, $searchField.val())
                search = $searchField.val();
            });
        });

        if($element.is(`[autofocus]:visible`)) {
            $element.select2(`open`);
        }
    }

    static includeParams($element, params) {
        if($element.is(`[data-include-params]`)) {
            const selector = $element.data(`include-params`);
            const closest = $element.data(`include-params-parent`) || `.modal, .wii-form`;
            const $fields = $element
                .closest(closest)
                .find(selector);

            const values = $fields
                .filter((_, elem) => elem.name && elem.value)
                .keymap((elem) => [elem.name, elem.value], GROUP_WHEN_NEEDED);

            params = {
                ...params,
                ...values,
            };
        }

        return params;
    }
}

$(document).ready(() => $(`[data-s2]`).each((id, elem) => {
    Select2.init($(elem))
}));

$(document).arrive(`[data-s2]`, function() {
    Select2.init($(this));
});
