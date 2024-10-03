
import AJAX, {GET} from "@app/ajax";
import moment from 'moment';

const PLANNING_DATA = 'wii-planning';
export const PLANNING_EVENT_LOADED = 'wii-planning-loaded';

export default class Planning {
    $container;
    route;
    baseDate;
    step;
    params;

    /**
     * @param {jQuery} $container
     * @param {string} route
     * @param {moment.Moment} baseDate
     * @param {number} step
     * @param {function} params
     */
    constructor($container, {route, params, baseDate = moment(), step = 1}) {
        this.$container = $container;
        this.route = route;
        this.baseDate = baseDate;
        this.step = step;
        this.params = params;

        this.$container
            .addClass(PLANNING_DATA)
            .data(PLANNING_DATA, this);
    }

    fetch() {
        const params = this.params || {};
        return AJAX
            .route(GET, this.route, {
                startDate: this.baseDate.format('YYYY-MM-DD'),
                step: this.step,
                ...params,
            })
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

                const $expandedCards = $(`[name=expandedCards]`);
                if($expandedCards.exists() && $expandedCards.val()) {
                    const expandedCards = $expandedCards
                        .val()
                        .split(`;`)
                        .map((id) => `.planning-card[data-id=${id}] .collapse`)
                        .join(`, `);

                    this.$container.find(expandedCards).collapse('show');
                }

                this.$container.trigger(PLANNING_EVENT_LOADED);
            });
    }

    onPlanningLoad(callback) {
        this.$container.on(PLANNING_EVENT_LOADED, function() {
            callback(event, this);
        });
    }

    resetBaseDate(weekBeginning = false) {
        this.baseDate = weekBeginning
            ? moment().startOf(`isoWeek`)
            : moment();
        return this.fetch();
    }

    previousDate() {
        this.baseDate = this.baseDate.subtract(this.step, 'days');
        return this.fetch();
    }

    nextDate() {
        this.baseDate = this.baseDate.add(this.step, 'days');
        return this.fetch();
    }

    previousWeek() {
        this.baseDate = this.baseDate.subtract(1, `weeks`).startOf(`isoWeek`);
        return this.fetch();
    }

    nextWeek() {
        this.baseDate = this.baseDate.add(1, `weeks`).startOf(`isoWeek`);
        return this.fetch();
    }
}
