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
        route: '/emplacement/form',
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
        route: '/nature/api',
        alias: 'nature_api'
    },
    nature_new: {
        method: 'POST',
        route: '/nature/new',
        alias: 'nature_new'
    },
    nature_edit: {
        method: 'POST',
        route: '/natureedit',
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
    },
    settings_save: {
        method: 'POST',
        route: '/parametrage/enregistrer',
        alias: 'settings_save'
    },
    settings_free_field_api: {
        method: 'GET',
        route: '/parametrage/champs-libres/api/*',
        alias: 'settings_free_field_api'
    },
    pack_api: {
        method: 'POST',
        route: 'arrivage/packs/api/*',
        alias: 'packs_api'
    },
    print_arrivage_bar_codes_nature_1:{
        method: 'GET',
        route: '/arrivage/*/etiquettes?*template=1',
        alias: 'print_arrivage_bar_codes_nature_1'
    },
    print_arrivage_bar_codes_nature_2:{
        method: 'GET',
        route: '/arrivage/*/etiquettes?*template=2',
        alias: 'print_arrivage_bar_codes_nature_2'
    },
    arrivage_new:{
        method: 'POST',
        route: '/arrivage/creer',
        alias: 'arrivage_new'
    },
    new_dispute_template:{
        method: 'GET',
        route: '/arrivage/new-dispute-template*',
        alias: 'new_dispute_template'
    },
    dispute_new:{
        method: 'POST',
        route: '/arrivage/creer-litige*',
        alias: 'dispute_new'
    },
    arrival_diputes_api: {
        method: 'POST',
        route: '/arrivage/litiges/api/*',
        alias: 'arrival_diputes_api'
    },
    arrival_dispute_api_edit:{
        method: 'GET',
        route: '/arrivage/api-modifier-litige',
        alias: 'arrival_dispute_api_edit'
    },
    arrival_edit_dispute:{
        method: 'POST',
        route: '/arrivage/modifier-litige*',
        alias: 'arrival_edit_dispute'
    },
    arrivage_add_pack:{
        method: 'POST',
        route: '/arrivage/ajouter-UL',
        alias: 'arrivage_add_pack'
    },
    printPacks:{
        method: 'GET',
        route: '/arrivage/*/etiquettes?packs%5B%5D=*',
        alias: 'printPacks'
    },
    arrivage_edit_api:{
        method: 'POST',
        route: '/arrivage/api-modifier',
        alias: 'arrivage_edit_api'
    },
     arrivage_edit:{
        method: 'POST',
        route: '/arrivage/modifier',
        alias: 'arrivage_edit'
     }
};
export default routes;


/*
    * This function allows you to intercept a route and give it an alias.
    * @param {Object} route : The route object to intercept.
    * @example :
    * interceptRoute(routes.emplacement_api);
 */
export function interceptRoute(route) {
    // check if the route is defined
    try{
        cy.intercept(route.method, route.route).as(route.alias);
    }catch (e) {
        console.error('The route is not defined in the routes file : ' + e);
    }
}
