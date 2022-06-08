import AJAX, {GET} from "@app/ajax";
import '@styles/planning.scss';

const PLANNING_SELECTOR = 'wii-planning'

export default class Planning {
    $container;

    constructor() {
        this.$container = $(`[data-${PLANNING_SELECTOR}]`);
        this.$container.data(`${PLANNING_SELECTOR}-instance`, this);

        this.fetch();
    }

    fetch() {
        const route = this.$container.data(PLANNING_SELECTOR);
        AJAX.route(GET, route)
            .json()
            .then(({template}) => {
                this.$container.html(template);
                this.$container.trigger('planning-loaded');
            });
    }
}
