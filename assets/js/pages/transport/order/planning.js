import '@styles/planning.scss';
import '@styles/pages/transport/planning.scss';
import Form from "@app/form";
import moment from 'moment';
import Planning from "@app/planning";

let planning;

$(function () {
    initializeRoundPlan();

    planning = new Planning($('.transport-planning'), {
        route: 'transport_planning_api',
        step: 2,
        params: () => ({
            statuses: $('[name=status]:checked')
                .map((_, input) => $(input).val())
                .toArray()
                .join(',')
        })
    });

    const $button = $('.planning-switch').find('[name=status]');

    $button.on('change', function (){
        const $filter = $(this);
        const $container = $filter.parent();
        // at least on filter checked
        if (!$('[name=status]:checked').exists()) {
            $filter.prop('checked', true);
        }

        wrapLoadingOnActionButton($container, () => planning.fetch())
    });

    $('.today-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.resetBaseDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.decrement-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.previousDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });

    $('.increment-date').on('click', function () {
        wrapLoadingOnActionButton($(this), () => (
            planning.nextDate()
                .then(() => {
                    changeNavigationButtonStates();
                })
        ));
    });
});

function initializeRoundPlan() {
    const $modalRoundPlan = $('#modalRoundPlan');
    $(document).on("click", ".plan-round-button", function() {
        $modalRoundPlan.modal('show');
    });

    Form.create($modalRoundPlan)
        .onOpen(() => {
            const $round = $modalRoundPlan.find('[name=round]');
            const $date = $modalRoundPlan.find('[name=date]');
            const $radios = $modalRoundPlan.find('[name=roundInfo]');
            const $newRoundRadio = $radios.filter('[value=newRound]');

            $radios.on('change', function(){
                if ($(this).val() === 'newRound') {
                    $round
                        .prop('disabled', true)
                        .prop('required', false);
                    $date
                        .prop('disabled', false)
                        .prop('required', true);
                }
                else {
                    $round
                        .prop('disabled', false)
                        .prop('required', true);
                    $date
                        .prop('disabled', true)
                        .prop('required', false);
                }
            });

            $newRoundRadio
                .prop('checked', true)
                .trigger('change');
        })
        .onSubmit(function(data) {
            wrapLoadingOnActionButton($modalRoundPlan.find('[type=submit]'), () => {
                return submitRoundModal(data);
            });
        });
}

function submitRoundModal(data) {
    const roundInfo = data.get('roundInfo');
    const params = {};
    if (roundInfo === 'newRound') {
        params.dateRound = data.get('date');
        AJAX.route(GET, 'is-order-for-date', {'date' : params.dateRound }).json().then((result) => {
            if (result) {
                window.location.href = Routing.generate('transport_round_plan', params);
            } else {
                Flash.add('danger', 'Aucun ordre n’est à faire pour cette date');
            }
        })
    }
    else if (roundInfo === 'editRound') {
        params.transportRound = data.get('round');
        window.location.href = Routing.generate('transport_round_plan', params);
    }
    return Promise.resolve();
}

function changeNavigationButtonStates() {
    const $todayDate = $('.today-date');
    const $decrementDate = $('.decrement-date');
    const $incrementDate = $('.increment-date');

    $decrementDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
    $todayDate.prop('disabled', moment().isSame(planning.baseDate, 'day'));
    $incrementDate.prop('disabled', moment().add(6, 'days').isSame(planning.baseDate, 'day'));
}
