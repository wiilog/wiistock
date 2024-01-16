import Select2 from "../../select2";

$(function () {
    const $modalNewMvtTraca = $('#modalNewMvtTraca');

    $modalNewMvtTraca.on('change', '[name=emplacement-prise]', function () {
        $modalNewMvtTraca.find('[name=pack]').trigger('change');
    })

    $modalNewMvtTraca.on('ready', 'select[name=pack]', function () {
        const $warningMessage = $modalNewMvtTraca.find('.warning-message');
        const $select = $(this);
        let $locationSelect = $modalNewMvtTraca.find('[name=emplacement-prise]');
        Select2.initSelectMultipleWarning(
            $select,
            $warningMessage,
            async ($option) => {
                if ($option.data('location') === undefined) {
                    await AJAX.route(
                        AJAX.GET,
                        'pack_get_location',
                        {pack: $option.val()},
                    ).json().then(function (response) {
                        $option.data('location', response.location);
                    });
                }
                return parseInt($option.data('location')) === parseInt($locationSelect.val());
            },
            {});
    });

    $modalNewMvtTraca.on('ready', 'input[name=pack]', function () {
        const $warningMessage = $modalNewMvtTraca.find('.warning-message');
        const $input = $(this);
        let $locationSelect = $modalNewMvtTraca.find('[name=emplacement]');

        $.merge($input, $locationSelect).on('change', function () {
            if ($input.val() !== '' && $locationSelect.val()) {
                AJAX.route(
                    AJAX.GET,
                    'pack_get_location',
                    {pack: $input.val()},
                ).json().then(function (response) {
                    $warningMessage.prop('hidden', parseInt(response.location) === parseInt($locationSelect.val()));
                });
            } else {
                $warningMessage.prop('hidden', true);
            }
        });

    });
})

