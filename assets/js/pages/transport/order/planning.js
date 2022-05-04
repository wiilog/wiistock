import '@styles/pages/transport/planning.scss';
import {GET} from "@app/ajax";

$(function () {
    getOrders();
})

function getOrders(){
    AJAX.route(GET,'transport_planning_api').json().then(({template})=>{
        $('.planning-container').empty().append(template);
    });
}
