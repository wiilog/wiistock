import '@styles/pages/transport/planning.scss';
import AJAX, {GET} from "@app/ajax";

let currentDate = moment();
$(function () {
    getOrders();
    initializeRoundPlan(() => {
    });

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
        });
}

export function submitRoundModal(data) {
    const roundInfo = data.get('roundInfo');
    const params = {};
    if(roundInfo === 'newRound') {
        params['dateRound'] = data.get('date');
    }
    else if (roundInfo === 'editRound') {
        params['transportRound'] = data.get('rounds');
    }
    window.location.href = Routing.generate('transport_round_plan', params);
}
