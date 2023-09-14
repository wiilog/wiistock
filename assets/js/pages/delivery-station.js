import '@styles/pages/kiosk.scss';
import '@styles/pages/delivery-station.scss';

$(function () {
    $(`button.login`).on(`click`, function () {
        const mobileLoginKey = $(`[name=mobileLoginKey]`).val();
        const type = $(`[name=type]`).val();
        const visibilityGroup = $(`[name=visibilityGroup]`).val();

        if(mobileLoginKey) {
            wrapLoadingOnActionButton($(this), () => (
                AJAX.route(AJAX.POST, `delivery_station_login`, {mobileLoginKey})
                    .json()
                    .then(({success}) => {
                        if(success) {
                            window.location.href = Routing.generate(`delivery_station_form`, {mobileLoginKey, type, visibilityGroup});
                        } else {
                            toggleRequiredMobileLoginKeyModal();
                        }
                    })
            ));
        } else {
            toggleRequiredMobileLoginKeyModal();
        }
    });

    $('.button-next').on('click', function () {
        const $current = $(this).closest('.stock-exit-container').find('.active');
        const $buttonNextContainer = $(this).parent();
        const $timeline = $(`.timeline-container`);
        const $currentTimelineEvent = $timeline.find(`.current`);
        const $inputs = $current.find(`input[required]`);
        const $selects = $current.find(`select.needed`);

        $selects.each(function () {
            if ($(this).find('option:selected').length === 0) {
                $(this).parent().find('.select2-selection ').addClass('invalid');
            } else {
                $(this).parent().find('.select2-selection ').removeClass('invalid');
            }
        });

        $inputs.each(function () {
            if (!($(this).val())) {
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });

        if($current.find(`.invalid`).length === 0) {
            if($current.is(`.reference-choice-container`)) {
                //const reference = $(`[name=reference]`).val();
                const reference = 7646;

                wrapLoadingOnActionButton($(this), () => (
                    AJAX.route(AJAX.GET, `delivery_station_get_reference_informations`, {reference})
                        .json()
                        .then(({values}) => {
                            console.log(values);
                            pushNextPage($current);
                            updateTimeline($currentTimelineEvent);
                            updateReferenceInformations(values);
                        })
                ));
            }
        }
    });

    $('.return-or-give-up-button').on('click', function () {
        const $current = $('.active')
        const $timeline = $('.timeline-container');
        const $currentTimelineEvent = $timeline.find('.current');
        const $modalGiveUpStockExit = $(`.modal-give-up-stock-exit`);

        if (!$current.prev().first().is(`body`)) {
            $currentTimelineEvent.addClass('future').removeClass('current');
            $($currentTimelineEvent.prev()[0]).addClass('current').removeClass('future');
            $current.removeClass('active').addClass('d-none');
            $($current.prev()[0]).addClass('active').removeClass('d-none');
        } else {
            $modalGiveUpStockExit.modal('show');
            $modalGiveUpStockExit.find('.bookmark-icon').removeClass('d-none');
        }
    });

    $(`#submitGiveUpStockExit`).on('click', () => {
        window.location.href = Routing.generate('delivery_station_index', true);
    });
});



function toggleRequiredMobileLoginKeyModal() {
    $(`.modal-required-mobile-login-key`).modal(`show`);
}

function updateTimeline($currentTimelineEvent) {
    $currentTimelineEvent
        .removeClass(`current`);

    $currentTimelineEvent
        .next()
        .removeClass(`future`)
        .addClass(`current`);
}

function pushNextPage($current) {
    $current
        .addClass(`d-none`)
        .removeClass(`active`);

    $current
        .next()
        .removeClass(`d-none`)
        .addClass(`active`);
}

function updateReferenceInformations(values) {
    const $referenceInformations = $(`.reference-informations`);
    for (const [index, value] of Object.entries(values)) {
        if(index === `image` && value) {
            $referenceInformations.find(`.${index}`).attr(`src`, value).removeAttr(`style`);
        } else {
            $referenceInformations.find(`.${index}`).text(value);
        }
    }

    $(`.quantity-choice-container .location`).text(values.location);
}
