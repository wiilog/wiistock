import AJAX, {GET} from "@app/ajax";
import '@styles/planning.scss';

const PLANNING_SELECTOR = 'wii-planning'

export default class Planning {
    static initialize() {
        initializePlanning($(`[data-${PLANNING_SELECTOR}]`));
        $(document).arrive(`[data-${PLANNING_SELECTOR}]`, function() {
            initializePlanning($(this));
        });
    }
}

function initializePlanning($container) {
    if ($container.length > 0) {
        const route = $container.data(PLANNING_SELECTOR);
        AJAX.route(GET, route)
            .json()
            .then(({template}) => {
                $container.html(template);
            });
    }
}
