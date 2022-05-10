import '@styles/pages/transport/planning.scss';
import {GET} from "@app/ajax";
import moment from 'moment';

let currentDate = moment();

$(function () {
    getOrders();

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
