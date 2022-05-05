import '@styles/pages/transport/planning.scss';
import {GET} from "@app/ajax";

let currentDate = moment();
$(function () {
    getOrders();

    $('.planning-switch').children().each(function(){
        $(this).on('change', function (){
            if($(this).find('input').is(':checked')){
                wrapLoadingOnActionButton($(this), () => getOrders($(this).attr('class'), $(this)));
            } else {
                wrapLoadingOnActionButton($(this), () => getOrders(null, $(this)));
            }
        });
    });
    $('.today-date').on('click', function (){
        currentDate = moment();
        wrapLoadingOnActionButton($(this), () => getOrders(null, $(this)));
    });

    $('.increment-date').on('click', function (){
        currentDate = currentDate.add(1, 'days');
        wrapLoadingOnActionButton($(this), () => getOrders(null, $(this)));
    });

    $('.decrement-date').on('click', function (){
        currentDate = currentDate.subtract(1, 'days');
        wrapLoadingOnActionButton($(this), () => getOrders(null, $(this)));
    });
})

function getOrders(statut = null, $button = null){
    return AJAX.route(GET,'transport_planning_api',{
        'statusForFilter': statut,
        'currentDate': currentDate.format('YYYY-MM-DD')
    }).json().then(({template})=>{
        $('.planning-container').empty().append(template);
        if($button !== null){
            $button.popLoader();
        }
    });
}
