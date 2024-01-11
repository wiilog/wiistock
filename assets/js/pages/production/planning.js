import '@styles/planning.scss';
import Planning from "@app/planning";

let planning = null;
$(function () {
    planning = new Planning($('.production-planning'), {route: 'production_planning_api', step: 5});
});
