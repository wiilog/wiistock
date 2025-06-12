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

    truck_arrival_api: {
        method: 'GET',
        route: '/arrivage-camion/truck-arrival-lines-api*',
        alias: 'lines_api'
    },

    number_carrier_api: {
        method: 'GET',
        route: '/select/truck-arrival-line-number',
        alias: 'ajax_select_truck_arrival_line'
    },

    truck_arrival_list: {
        method: 'POST',
        route: '/arrivage-camion/api-list',
        alias: 'truck_arrival_api_list'
    },

    emplacement_new: {
        method: 'POST',
        route: '/emplacement/creer',
        alias: 'emplacement_new'
    },
    location_form_new: {
        method: 'GET',
        route: '/emplacement/form',
        alias: 'location_form_new'
    },
    location_form_edit: {
        method: 'GET',
        route: '/emplacement/form/*',
        alias: 'location_form_edit'
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
        route: '/nature/edit',
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
    arrival_packs_api: {
        method: 'POST',
        route: 'arrivage/*/packs-api',
        alias: 'arrival_packs_api'
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
        route: '/arrivage/api-modifier-litige/*',
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
        method: 'GET',
        route: '/arrivage/api-modifier?id=*',
        alias: 'arrivage_edit_api'
    },
    arrivage_edit:{
        method: 'POST',
        route: '/arrivage/modifier',
        alias: 'arrivage_edit'
    },
    production_new:{
        method: 'POST',
        route: '/production/new',
        alias: 'production_new'
    },
    production_api: {
        method: 'POST',
        route: '/production/api*',
        alias: 'production_api'
    },
    production_edit: {
        method: 'POST',
        route: 'production/*/edit',
        alias: 'production_edit'
    },
    production_operation_history_api: {
        method: 'GET',
        route: 'production/*/operation-history-api',
        alias: 'production_operation_history_api'
    },
    production_status_history_api: {
        method: 'GET',
        route: 'production/*/status-history-api',
        alias: 'production_status_history_api'
    },
    production_update_status_content: {
        method: 'GET',
        route: 'production/*/update-status-content',
        alias: 'production_update_status_content'
    },
    production_update_status: {
        method: 'POST',
        route: 'production/*/update-status',
        alias: 'production_update_status'
    },
    production_delete: {
        method: 'DELETE',
        route: 'production/delete/*',
        alias: 'production_delete'
    },
    production_request_planning_api_test: {
        method: 'GET',
        route: "production/planning/*",
        alias: 'production_request_planning_api_test'
    },

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
