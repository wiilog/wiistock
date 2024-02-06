/* This file allow you to define all the routes of your application in one place.
 * This is useful to avoid hardcoding URLs in your tests and to make your tests more maintainable.
*/
export const routes = {
    emplacement_api: {
        method: 'POST',
        route: '/emplacement/api',
        alias: 'emplacement_api'
    },
    emplacements_groupes_api: {
        method: 'POST',
        route: '/emplacements/groupes/api',
        alias: 'emplacements_groupes_api'
    },
    zones_api: {
        method: 'POST',
        route: '/zones/api',
        alias: 'zones_api'
    },
    emplacement_new: {
        method: 'POST',
        route: '/emplacement/creer',
        alias: 'emplacement_new'
    },
    location_api_new: {
        method: 'GET',
        route: '/emplacement/api-new',
        alias: 'location_api_new'
    },
    emplacement_edit: {
        method: 'POST',
        route: '/emplacement/edit',
        alias: 'emplacement_edit'
    },
    location_group_new: {
        method: 'POST',
        route: '/emplacements/groupes/creer',
        alias: 'location_group_new'
    },
    location_group_edit: {
        method: 'POST',
        route: '/emplacements/groupes/modifier',
        alias: 'location_group_edit'
    },
    zone_new: {
        method: 'POST',
        route: '/zones/creer',
        alias: 'zone_new'
    },
    zone_edit: {
        method: 'POST',
        route: '/zones/modifier',
        alias: 'zone_edit'
    },
    transporteur_api: {
        method: 'POST',
        route: '/transporteur/api',
        alias: 'transporteur_api'
    },
    transporteur_save: {
        method: 'POST',
        route: '/transporteur/save',
        alias: 'transporteur_save'
    },
    transporteur_save_edit: {
        method: 'POST',
        route: '/transporteur/save?*',
        alias: 'transporteur_save_edit'
    },
    chauffeur_new: {
        method: 'POST',
        route: '/chauffeur/creer',
        alias: 'chauffeur_new'
    },
    chauffeur_edit: {
        method: 'POST',
        route: '/chauffeur/modifier',
        alias: 'chauffeur_edit'
    },
    nature_api: {
        method: 'POST',
        route: '/nature-unite-logistique/api',
        alias: 'nature_api'
    },
    nature_new: {
        method: 'POST',
        route: '/nature-unite-logistique/creer',
        alias: 'nature_new'
    },
    nature_edit: {
        method: 'POST',
        route: '/nature-unite-logistique/modifier',
        alias: 'nature_edit'
    },
    vehicule_api: {
        method: 'POST',
        route: '/vehicule/api',
        alias: 'vehicule_api'
    },
    vehicule_new: {
        method: 'POST',
        route: '/vehicule/new',
        alias: 'vehicule_new'
    },
    vehicle_edit: {
        method: 'POST',
        route: '/vehicule/edit',
        alias: 'vehicle_edit'
    },
    project_api: {
        method: 'POST',
        route: '/project/api',
        alias: 'project_api'
    },
    project_new: {
        method: 'POST',
        route: '/project/new',
        alias: 'project_new'
    },
    project_edit: {
        method: 'POST',
        route: '/project/edit',
        alias: 'project_edit'
    },
    customer_api: {
        method: 'POST',
        route: '/clients/api',
        alias: 'customer_api'
    },
    customer_new: {
        method: 'POST',
        route: '/clients/new',
        alias: 'customer_new'
    },
    customer_edit: {
        method: 'POST',
        route: '/clients/edit',
        alias: 'customer_edit'
    }
};
export default routes;
