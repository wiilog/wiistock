import sortable from '../../node_modules/html5sortable/dist/html5sortable.es.js';
import $ from 'jquery';

const SORTABLE_CONTAINER_CLASS = 'wii-sortable-container';

export default class Sortable {
    static create(selector, config) {
        const sortables = sortable(selector, {
            placeholderClass: 'placeholder-container',
            forcePlaceholderSize: true,
            placeholder: `
                <div class="placeholder-container">
                    <div class="placeholder"></div>
                </div>
            `,
            ...config,
        });

        $(sortables).addClass(SORTABLE_CONTAINER_CLASS);

        //fix a bug in which sorting triggers the event but doesn't
        //actually move the item in the new container





        return sortables;
    }
}
