import $ from 'jquery';
import 'select2';
import {GROUP_WHEN_NEEDED} from "./app";

const ROUTES = {
    handlingType: `ajax_select_handling_type`,
    deliveryType: `ajax_select_delivery_type`,
    collectType: `ajax_select_collect_type`,
    status: `ajax_select_status`,
    location: `ajax_select_locations`,
    sensor: `ajax_select_sensors`,
    sensorWrapper: `ajax_select_sensor_wrappers`,
    reference: `ajax_select_references`,
    packWithoutPairing: `ajax_select_packs_without_pairing`,
    articleWithoutPairing: `ajax_select_articles_without_pairing`,
    locationWithoutPairing: `ajax_select_locations_without_pairing`,
}

const INSTANT_SELECT_TYPES = {
    handlingType: true,
    deliveryType: true,
    collectType: true,
    status: true,
}

export default class Select2 {
    static init($element) {
        const type = $element.data(`s2`);
        if(!$element.find(`option[selected]`).exists() && !type &&
            !$element.is(`[data-no-empty-option]`) && !$element.is(`[data-editable]`)) {
            $element.prepend(`<option selected>`);
        }

        $element.removeAttr(`data-s2`);
        $element.attr(`data-s2-initialized`, ``);

        const config = {};
        if(type) {
            if(!ROUTES[type]) {
                console.error(`No select route found for ${type}`);
            }

            config.ajax = {
                url: Routing.generate(ROUTES[type]),
                data: params => Select2.includeParams($element, params),
                dataType: `json`
            };
        }

        if (type && !INSTANT_SELECT_TYPES[type]) {
            config.minimumInputLength = 1;
        }

        $element.select2({
            placeholder: $element.data(`placeholder`),
            tags: $element.is('[data-editable]'),
            allowClear: !$element.is(`[multiple]`),
            dropdownParent: $element.parent(),
            language: {
                inputTooShort: () => 'Veuillez entrer au moins 1 caractère.',
                noResults: () => `Aucun résultat`,
                searching: () => null,
            },
            ...config,
        });

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

            const $select2Parent = $element.parent();
            const $searchField = $select2Parent.find('.select2-search--dropdown .select2-search__field');
            if ($searchField.exists()) {
                setTimeout(() => $searchField[0].focus(), 300);
            }
        });
    }

    static includeParams($element, params) {
        if($element.is(`[data-include-params]`)) {
            const selector = $element.data(`include-params`);
            const closest = $element.data(`[data-include-params-parent]`) || `.modal`;
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

$(document).ready(() => $(`[data-s2]`).each((id, elem) => Select2.init($(elem))));
$(document).arrive(`[data-s2]`, function() {
    Select2.init($(this));
});
