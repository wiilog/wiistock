import '@styles/pages/transport/planning.scss';
import AJAX, {GET} from "@app/ajax";
import moment from 'moment';

let currentDate = moment();

$(function () {
    getOrders();
    initializeRoundPlan();

    const $button = $('.planning-switch').find('[name=status]');

    $button.on('change', function (){
        const $filter = $(this);
        const $container = $filter.parent();
        // at least on filter checked
        if (!$('[name=status]:checked').exists()) {
            $filter.prop('checked', true);
        }

        wrapLoadingOnActionButton($container, () => getOrders())
    });

    $('.today-date').on('click', function (){
        currentDate = moment();
        $('.decrement-date').prop('disabled', false);
        $('.increment-date').prop('disabled', false);

        wrapLoadingOnActionButton($(this), () => getOrders());
    });

    $('.decrement-date').on('click', function () {
        changeCurrentDay('down');
        wrapLoadingOnActionButton($(this), () => getOrders());
    });

    $('.increment-date').on('click', function () {
        changeCurrentDay('up');
        wrapLoadingOnActionButton($(this), () => getOrders());
    });
})

function getOrders() {
    const statuses = $('[name=status]:checked')
        .map((_, input) => $(input).val())
        .toArray()
        .join(',');
    return AJAX
        .route(GET,'transport_planning_api',{
            'statuses': statuses,
            'currentDate': currentDate.format('YYYY-MM-DD')
        })
        .json()
        .then(({template})=>{
            $('.planning-container').html(template);
        });
}

export function initializeRoundPlan() {
    const $modalRoundPlan = $('#modalRoundPlan');
    $(document).on("click", ".plan-round-button", function() {
        const $button = $(this);
        wrapLoadingOnActionButton($button, () => openPlanRoundModal($modalRoundPlan));
    });

    Form.create($modalRoundPlan).onSubmit(function(data) {
        wrapLoadingOnActionButton($modalRoundPlan.find('[type=submit]'), () => {
            return submitRoundModal(data);
        });
    })
}

export function openPlanRoundModal($modalRoundPlan) {
    const $modalBody = $modalRoundPlan.find('.modal-body');
    $modalBody.html(`
        <div class="row justify-content-center">
             <div class="col-auto">
                <div class="spinner-border" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
             </div>
        </div>
    `);

    $modalRoundPlan.modal('show');
    return AJAX.route(GET, `plan_round_api`)
        .json()
        .then((result) => {
            if (result && result.success) {
                $modalBody.html(result.html);
            }
            buttonModalManagement();
        });
}

export function submitRoundModal(data) {
    const roundInfo = data.get('roundInfo');
    const params = {};
    if(roundInfo === 'newRound') {
        params['dateRound'] = data.get('date');
    }
    else if (roundInfo === 'editRound') {
        params['transportRound'] = data.get('round');
    }
    window.location.href = Routing.generate('transport_round_plan', params);
}

function buttonModalManagement() {
    $('.roundInfo-date').attr('checked', 'checked');
    $('[name=roundInfo]').change(function(){
        if($('.roundInfo-number').is(':checked')){
            $('[name=round]').attr('disabled', false).attr('required', true);
        }
        else {
            $('[name=round]').attr('disabled', true).attr('required', false);
        }
    });
}

/**
 * @param {"up"|"down"|"today"} direction
 */
function changeCurrentDay(direction) {
    const $decrementDate = $('.decrement-date');
    const $incrementDate = $('.increment-date');
    const $todayDate = $('.today-date');

    let changed = false;

    if (direction === 'down' && !moment().isSame(currentDate, 'day')) {
        currentDate = currentDate.subtract(2, 'days');
        changed = true;
    }
    else if (direction === 'up' && !moment().add(6, 'days').isSame(currentDate, 'day')) {
        currentDate = currentDate.add(2, 'days');
        changed = true;
    }
    else if (direction === 'today' && !moment().isSame(currentDate, 'day')) {
        currentDate = moment();
        changed = true;
    }

    $todayDate.prop('disabled', moment().isSame(currentDate, 'day'));
    $decrementDate.prop('disabled', moment().isSame(currentDate, 'day'));
    $incrementDate.prop('disabled', moment().add(6, 'days').isSame(currentDate, 'day'));
    return changed;
}
