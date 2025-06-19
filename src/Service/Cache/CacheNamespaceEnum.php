<?php

namespace App\Service\Cache;

enum CacheNamespaceEnum: string {

    case PERMISSIONS = "permissions";
    case TRANSLATIONS = "translations";
    case LANGUAGES = "languages";
    case EXPORTS = "exports";
    case IMPORTS = "imports";
    case PURCHASE_REQUEST_PLANS = "purchase-request-plans";
    case INVENTORY_MISSION_PLANS = "inventory-mission-plans";
    case SETTINGS = "settings";
    case WORK_PERIOD = "work-period";
    case ENTITIES_DICTIONARY = "entities";
    case SLEEPING_STOCK_PLANS = "sleeping-stock-plans";
    case ARRIVAL_PRINT_PACK = "arrival-print-pack";

}
