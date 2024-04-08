import Select2 from "../../select2";

$(function () {
    const $modalNewMvtTraca = $('#modalNewMvtTraca');

    $modalNewMvtTraca.on('change', '[name=emplacement-prise]', function () {
        $modalNewMvtTraca.find('[name=pack]').trigger('change');
    })

    $modalNewMvtTraca.arrive('select[name=pack]', function() {
        const $warningMessage = $modalNewMvtTraca.find('.warning-message');
        const $select = $(this);
        let $locationSelect = $modalNewMvtTraca.find('[name=emplacement-prise]');

        if($locationSelect.length > 0){
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
        }
    });
})

