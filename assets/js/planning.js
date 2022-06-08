import AJAX, {GET} from "@app/ajax";
import '@styles/planning.scss';

const PLANNING_SELECTOR = 'wii-planning'

export default class Planning {
    $container;

    static initialize() {
        initializePlanning(this, $(`[data-${PLANNING_SELECTOR}]`));

        $(document).arrive(`[data-${PLANNING_SELECTOR}]`, function () {
            initializePlanning(this, $(this));
        });
    }

    reload(filters) {
        // TODO use filter
        initializePlanning(this, this.$container);
    }
}

function initializePlanning(planning, $container) {
    if ($container.length > 0) {
        planning.$container = $container;
        $container.data(`${PLANNING_SELECTOR}-instance`, planning);

        const route = $container.data(PLANNING_SELECTOR);
        AJAX.route(GET, route)
            .json()
            .then(({template}) => {
                $container.html(template);
                $container.trigger('planning-loaded');
            });
    }
}
