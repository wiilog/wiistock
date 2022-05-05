import '@styles/pages/transport/planning.scss';
import AJAX, {GET} from "@app/ajax";

$(function () {
    getOrders();
    initializeRoundPlan(() => {
    });
})

function getOrders(){
    AJAX.route(GET,'transport_planning_api').json().then(({template})=>{
        $('.planning-container').empty().append(template);
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
