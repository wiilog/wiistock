import AJAX, {GET} from "@app/ajax";
import '@styles/planning.scss';

const PLANNING_DATA = 'wii-planning';
export const PLANNING_EVENT_LOADED = 'wii-planning-loaded';

export default class Planning {
    $container;

    constructor($container, route) {
        this.$container = $container;
        this.route = route;

        this.$container
            .addClass(PLANNING_DATA)
            .data(PLANNING_DATA, this);

        this.fetch();
    }

    fetch() {
        AJAX.route(GET, this.route)
            .json()
            .then(({template}) => {
                const $template = $(template);
                const $planningContainer = this.$container.find('.planning-container');
                if ($planningContainer.exists()) {
                    $planningContainer.replaceWith($template);
                }
                else {
                    this.$container.append($template);
                }
                this.$container.trigger(PLANNING_EVENT_LOADED);
            });
    }

    onPlanningLoad(callback) {
        this.$container.on(PLANNING_EVENT_LOADED, function() {
            callback(event, this)
        });
    }
}
