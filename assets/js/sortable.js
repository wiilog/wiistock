import sortable from '../../node_modules/html5sortable/dist/html5sortable.es.js';
import $ from 'jquery';

export default class Sortable {
    static create(selector, config) {
        const sortables = sortable(selector, config);

        //fix a bug in which sorting triggers the event but doesn't
        //actually move the item in the new container

        return sortables;
    }
}
