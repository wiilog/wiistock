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
        wrapLoadingOnActionButton($(this), () => updateFilter($(this)));
    });

    $('.increment-date').on('click', function (){
       if( currentDate < moment().add(6, 'days')){
            currentDate = currentDate.add(2, 'days');
            wrapLoadingOnActionButton($(this), () => updateFilter($(this)));
            $('.decrement-date').attr('disabled', false);
       }else{
           $(this).attr('disabled', true);
       }
    });

    $('.decrement-date').on('click', function (){
        if( currentDate > moment().subtract(6, 'days')) {
            currentDate = currentDate.subtract(2, 'days');
            wrapLoadingOnActionButton($(this), () => updateFilter($(this)));
            $('.increment-date').attr('disabled', false);
        }else{
            $(this).attr('disabled', true);
        }
    });
})

function updateFilter( $button = null){
    if(!$('.planning-switch').find('input:checked').length){
        return(getOrders(null , $button));
    }
    else {
        let response = null ;
        $('.planning-switch').children().each(function(){
            if($(this).find('input').is(':checked')){
                response = getOrders($(this).attr('class'), $(this));
            }
        });
        if($button !== null){
            $button.popLoader();
        }
        return (response ? response : Promise.resolve());
    }
}

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
