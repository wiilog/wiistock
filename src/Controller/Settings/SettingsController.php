<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\DeliveryStationLine;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fields\SubLineFixedField;
use App\Entity\FiltreRef;
use App\Entity\FreeField;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryMission;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\KioskToken;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\NativeCountry;
use App\Entity\Nature;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\ScheduledTask\Import;
use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use App\Entity\SessionHistoryRecord;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\TagTemplate;
use App\Entity\Translation;
use App\Entity\TranslationCategory;
use App\Entity\TranslationSource;
use App\Entity\Transport\CollectTimeSlot;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Transport\TransportRoundStartingHour;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Repository\IOT\AlertTemplateRepository;
use App\Repository\IOT\RequestTemplateRepository;
use App\Repository\SettingRepository;
use App\Repository\TypeRepository;
use App\Service\AttachmentService;
use App\Service\CacheService;
use App\Service\DispatchService;
use App\Service\FormService;
use App\Service\InvMissionService;
use App\Service\LanguageService;
use App\Service\PackService;
use App\Service\SessionHistoryRecordService;
use App\Service\SettingsService;
use App\Service\SpecificService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

/**
 * @Route("/parametrage")
 */
class SettingsController extends AbstractController {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public Twig_Environment $twig;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public StatusService $statusService;

    #[Required]
    public DispatchService $dispatchService;

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;

    #[Required]
    public SettingsService $service;

    #[Required]
    public UserService $userService;

    public const SETTINGS = [
        self::CATEGORY_GLOBAL => [
            "label" => "Global",
            "icon" => "menu-global",
            "menus" => [
                self::MENU_SITE_APPEARANCE => [
                    "label" => "Apparence du site",
                    "right" => Action::SETTINGS_DISPLAY_WEBSITE_APPEARANCE,
                    "save" => true,
                ],
                self::MENU_CLIENT => [
                    "label" => "Client application",
                    "right" => Action::SETTINGS_DISPLAY_APPLICATION_CLIENT,
                    "save" => true,
                    "environment" => ["dev", "preprod"],
                ],
                self::MENU_LABELS => [
                    "label" => "Étiquettes",
                    "right" => Action::SETTINGS_DISPLAY_BILL,
                    "save" => true,
                ],
                self::MENU_WORKING_HOURS => [
                    "label" => "Heures travaillées",
                    "right" => Action::SETTINGS_DISPLAY_WORKING_HOURS,
                ],
                self::MENU_OFF_DAYS => [
                    "label" => "Jours non travaillés",
                    "right" => Action::SETTINGS_DISPLAY_NOT_WORKING_DAYS,
                ],
                self::MENU_MAIL_SERVER => [
                    "label" => "Serveur email",
                    "right" => Action::SETTINGS_DISPLAY_MAIL_SERVER,
                    "save" => true,
                ],
            ],
        ],
        self::CATEGORY_STOCK => [
            "label" => "Stock",
            "icon" => "menu-stock",
            "menus" => [
                self::MENU_CONFIGURATIONS => [
                    "label" => "Configurations",
                    "right" => Action::SETTINGS_DISPLAY_CONFIGURATIONS,
                    "save" => true,
                ],
                self::MENU_ALERTS => [
                    "label" => "Alertes",
                    "right" => Action::SETTINGS_DISPLAY_STOCK_ALERTS,
                    "save" => true,
                ],
                self::MENU_ARTICLES => [
                    "label" => "Articles",
                    "right" => Action::SETTINGS_DISPLAY_ARTICLES,
                    "menus" => [
                        self::MENU_LABELS => [
                            "label" => "Étiquettes",
                            "save" => true,
                        ],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_TYPES_FREE_FIELDS => [
                            "label" => "Types et champs libres",
                            "wrapped" => false,
                        ],
                        self::MENU_NOMADE_RFID_CREATION => [
                            "label" => "Création nomade RFID",
                            "wrapped" => true,
                            "save" => true,
                        ],
                        self::MENU_NATIVE_COUNTRY => [
                            "label" => "Pays d'origine",
                            "save" => true,
                        ],
                    ],
                ],
                self::MENU_TOUCH_TERMINAL => [
                    "label" => "Borne tactile",
                    "right" => Action::SETTINGS_DISPLAY_TOUCH_TERMINAL,
                    "menus" => [
                        self::MENU_COLLECT_REQUEST_AND_CREATE_REF => [
                            "label" => "Demande collecte et création référence",
                            "save" => true,
                        ],
                        self::MENU_FAST_DELIVERY_REQUEST => [
                            "label" => "Demande livraison rapide",
                            "save" => true,
                        ],
                    ],
                ],
                self::MENU_REQUESTS => [
                    "label" => "Demandes",
                    "right" => Action::SETTINGS_DISPLAY_REQUESTS,
                    "menus" => [
                        self::MENU_DELIVERIES => [
                            "label" => "Livraisons",
                            "save" => true,
                        ],
                        self::MENU_DELIVERY_REQUEST_TEMPLATES => ["label" => "Livraisons - Modèle de demande", "wrapped" => false],
                        self::MENU_DELIVERY_TYPES_FREE_FIELDS => ["label" => "Livraisons - Types et champs libres", "wrapped" => false],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Livraisons - Champs fixes",
                            "save" => true,
                        ],
                        self::MENU_COLLECTS => [
                            "label" => "Collectes",
                            "save" => true,
                        ],
                        self::MENU_COLLECT_REQUEST_TEMPLATES => [
                            "label" => "Collectes - Modèle de demande", "wrapped" => false,
                        ],
                        self::MENU_COLLECT_TYPES_FREE_FIELDS => [
                            "label" => "Collectes - Types et champs libres", "wrapped" => false,
                        ],
                        self::MENU_PURCHASE_STATUSES => ["label" => "Achats - Statuts"],
                        self::MENU_PURCHASE_PLANIFICATION => [
                            "label" => "Achats - Planification",
                            "right" => Action::MANAGE_PURCHASE_REQUESTS_SCHEDULE_RULE,
                        ],
                        self::MENU_SHIPPING => [
                            "label" => "Expéditions",
                            "save" => true,
                        ],
                    ],
                ],
                self::MENU_VISIBILITY_GROUPS => [
                    "label" => "Groupes de visibilité",
                    "right" => Action::SETTINGS_DISPLAY_VISIBILITY_GROUPS,
                ],
                self::MENU_INVENTORIES => [
                    "label" => "Inventaires",
                    "right" => Action::SETTINGS_DISPLAY_INVENTORIES,
                    "menus" => [
                        self::MENU_INVENTORY_CONFIGURATION => [
                            "label" => "Configuration",
                            "save" => true,
                        ],
                        self::MENU_FREQUENCIES => ["label" => "Fréquences"],
                        self::MENU_CATEGORIES => ["label" => "Catégories"],
                        self::MENU_INVENTORY_PLANIFICATOR => ["label" => "Planificateur d'inventaire"],
                    ],
                ],
                self::MENU_RECEPTIONS => [
                    "label" => "Réceptions",
                    "right" => Action::SETTINGS_DISPLAY_RECEP,
                    "menus" => [
                        self::MENU_RECEPTIONS_STATUSES => [
                            "label" => "Réceptions - Statuts",
                            "save" => true,
                        ],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Réceptions - Champs fixes",
                            "save" => true,
                        ],
                        self::MENU_FREE_FIELDS => ["label" => "Réceptions - Champs libres"],
                        self::MENU_DISPUTE_STATUSES => ["label" => "Litiges - Statuts"],
                        self::MENU_DISPUTE_TYPES => [
                            "label" => "Litiges - Types",
                            "save" => true,
                        ],
                    ],
                ],
            ],
        ],
        self::CATEGORY_TRACING => [
            "label" => "Trace",
            "icon" => "menu-trace",
            "menus" => [
                self::MENU_DISPATCHES => [
                    "label" => "Acheminements",
                    "right" => Action::SETTINGS_DISPLAY_TRACING_DISPATCH,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => [
                            "label" => "Configurations",
                            "save" => true,
                        ],
                        self::MENU_STATUSES => ["label" => "Statuts", "wrapped" => false],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "save" => true,
                        ],
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres", "wrapped" => false],
                    ],
                ],
                self::MENU_ARRIVALS => [
                    "label" => "Arrivages UL",
                    "right" => Action::SETTINGS_DISPLAY_ARRI,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => [
                            "label" => "Configurations",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_LABELS => [
                            "label" => "Étiquettes",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_STATUSES => [
                            "label" => "Statuts",
                            "wrapped" => false,
                        ],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "save" => true,
                        ],
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres", "wrapped" => false],
                        self::MENU_DISPUTE_STATUSES => ["label" => "Litiges - Statuts"],
                        self::MENU_DISPUTE_TYPES => ["label" => "Litiges - Types"],
                    ],
                ],
                self::MENU_TRUCK_ARRIVALS => [
                    "label" => "Arrivages camion",
                    "right" => Action::SETTINGS_DISPLAY_TRUCK_ARRIVALS,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => [
                            "label" => "Configurations",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "save" => true,
                        ],
                        self::MENU_RESERVES => [
                            "label" => "Réserves",
                            "save" => false,
                        ],
                    ],
                ],
                self::MENU_BR_ASSOCIATION => [
                    "label" => "Association BR",
                    "right" => Action::SETTINGS_DISPLAY_BR_ASSOCIATION,
                    "save" => true,
                ],
                self::MENU_MOVEMENTS => [
                    "label" => "Mouvements",
                    "right" => Action::SETTINGS_DISPLAY_MOVEMENT,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => [
                            "label" => "Configurations",
                            "save" => true,
                        ],
                        self::MENU_FREE_FIELDS => ["label" => "Champs libres"],
                    ],
                ],
                self::MENU_HANDLINGS => [
                    "label" => "Services",
                    "right" => Action::SETTINGS_DISPLAY_TRACING_HAND,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => [
                            "label" => "Configurations",
                            "save" => true,
                        ],
                        self::MENU_STATUSES => ["label" => "Statuts", "wrapped" => false],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_REQUEST_TEMPLATES => ["label" => "Modèles de demande", "wrapped" => false],
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres", "wrapped" => false],
                    ],
                ],
                self::MENU_EMERGENCIES => [
                    "label" => "Urgences",
                    "right" => Action::SETTINGS_DISPLAY_EMERGENCIES,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => [
                            "label" => "Configurations",
                            "save" => true,
                        ],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "save" => true,
                            "discard" => true,
                        ],
                    ],
                ],
            ],
        ],
        self::CATEGORY_PRODUCTION => [
            "label" => "Production",
            "icon" => "production",
            "menus" => [
                self::MENU_FULL_SETTINGS => [
                    "label" => "Paramétrage complet",
                    "right" => Action::SETTINGS_DISPLAY_PRODUCTION,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => ["label" => "Configurations", "save" => true],
                        self::MENU_STATUSES => [
                            "label" => "Statuts",
                            "wrapped" => false,
                        ],
                        self::MENU_FIXED_FIELDS => [
                            "label" => "Champs fixes",
                            "wrapped" => true,
                            "save" => true,
                        ],
                        self::MENU_TYPES_FREE_FIELDS => [
                            "label" => "Types & champs libres",
                            "wrapped" => false,
                        ],
                    ],
                ],
            ],
        ],
        self::CATEGORY_TRACKING => [
            "label" => "Track",
            "icon" => "menu-track",
            "menus" => [
                self::MENU_TRANSPORT_REQUESTS => [
                    "label" => "Demandes",
                    "right" => Action::SETTINGS_DISPLAY_TRACK_REQUESTS,
                    "menus" => [
                        self::MENU_CONFIGURATIONS => ["label" => "Configurations", "save" => true],
                        self::MENU_DELIVERY_TYPES_FREE_FIELDS => [
                            "label" => "Livraisons - Types & champs libres",
                            "wrapped" => false,
                        ],
                        self::MENU_COLLECT_TYPES_FREE_FIELDS => [
                            "label" => "Collectes - Types & champs libres",
                            "wrapped" => false,
                        ],
                    ],
                ],
                self::MENU_ROUNDS => [
                    "label" => "Tournées",
                    "right" => Action::SETTINGS_DISPLAY_ROUND,
                    "save" => true,
                    "discard" => true,
                ],
                self::MENU_TEMPERATURES => [
                    "label" => "Températures",
                    "right" => Action::SETTINGS_DISPLAY_TEMPERATURES,
                    "save" => true,
                ],
            ],
        ],
        self::CATEGORY_MOBILE => [
            "label" => "Terminal mobile",
            "icon" => "menu-terminal-mobile",
            "menus" => [
                self::MENU_DISPATCHES => [
                    "label" => "Acheminements",
                    "right" => Action::SETTINGS_DISPLAY_MOBILE_DISPATCH,
                    "save" => true,
                ],
                self::MENU_HANDLINGS => [
                    "label" => "Services",
                    "right" => Action::SETTINGS_DISPLAY_MOBILE_HAND,
                    "save" => true,
                ],
                self::MENU_TRANSFERS => [
                    "label" => "Transferts à traiter",
                    "right" => Action::SETTINGS_DISPLAY_TRANSFER_TO_TREAT,
                    "save" => true,
                ],
                self::MENU_PREPARATIONS => [
                    "label" => "Préparations",
                    "right" => Action::SETTINGS_DISPLAY_PREPA,
                    "save" => true,
                ],
                self::MENU_PREPARATIONS_DELIVERIES => [
                    "label" => "Préparations / Livraisons ",
                    "right" => Action::SETTINGS_DISPLAY_PREPA_DELIV,
                    "save" => true,
                ],
                self::MENU_VALIDATION => [
                    "label" => "Gestion des validations",
                    "right" => Action::SETTINGS_DISPLAY_MANAGE_VALIDATIONS,
                    "save" => true,
                ],
                self::MENU_DELIVERIES => [
                    "label" => "Livraisons",
                    "right" => Action::SETTINGS_DISPLAY_DELIVERIES,
                    "save" => true,
                ],
            ],
        ],
        self::CATEGORY_DASHBOARDS => [
            "label" => "Dashboards",
            "icon" => "menu-dashboard",
            "menus" => [
                self::MENU_FULL_SETTINGS => [
                    "label" => "Paramétrage complet",
                    "right" => Action::SETTINGS_DISPLAY_DASHBOARD,
                    "route" => "dashboard_settings",
                ],
            ],
        ],
        self::CATEGORY_IOT => [
            "label" => "IoT",
            "icon" => "menu-iot",
            "menus" => [
                self::MENU_TYPES_FREE_FIELDS => [
                    "right" => Action::SETTINGS_DISPLAY_IOT,
                    "label" => "Types et champs libres",
                ],
            ],
        ],
        self::CATEGORY_NOTIFICATIONS => [
            "label" => "Modèles de notifications",
            "icon" => "menu-notification",
            "menus" => [
                self::MENU_ALERTS => [
                    "label" => "Alertes",
                    "right" => Action::SETTINGS_DISPLAY_NOTIFICATIONS_ALERTS,
                    "wrapped" => false,
                ],
                self::MENU_PUSH_NOTIFICATIONS => [
                    "label" => "Notifications push",
                    "right" => Action::SETTINGS_DISPLAY_NOTIFICATIONS_PUSH,
                ],
            ],
        ],
        self::CATEGORY_USERS => [
            "label" => "Utilisateurs",
            "icon" => "user",
            "menus" => [
                self::MENU_LANGUAGES => [
                    "label" => "Langues",
                    "right" => Action::SETTINGS_DISPLAY_LABELS_PERSO,
                    'route' => "settings_language_index",
                ],
                self::MENU_ROLES => [
                    "label" => "Rôles",
                    "right" => Action::SETTINGS_DISPLAY_ROLES,
                    "save" => false,
                ],
                self::MENU_USERS => [
                    "label" => "Utilisateurs",
                    "right" => Action::SETTINGS_DISPLAY_USERS,
                    "save" => false,
                ],
                self::MENU_SESSIONS => [
                    "label" => "Licences",
                    "right" => Action::SETTINGS_DISPLAY_SESSIONS,
                    "save" => false,
                ],
            ],
        ],
        self::CATEGORY_DATA => [
            "label" => "Données",
            "icon" => "menu-donnees",
            "menus" => [
                self::MENU_EXPORTS_ENCODING => [
                    "label" => "Encodage des exports CSV",
                    "right" => Action::SETTINGS_DISPLAY_EXPORT_ENCODING,
                    "save" => true,
                    "discard" => true,
                ],
                self::MENU_CSV_EXPORTS => [
                    "label" => "Exports CSV",
                    "right" => Action::SETTINGS_DISPLAY_EXPORT,
                    "save" => false,
                    "wrapped" => false,
                ],
                self::MENU_IMPORTS => [
                    "label" => "Imports & mises à jour",
                    "right" => Action::SETTINGS_DISPLAY_IMPORTS_MAJS,
                    "save" => false,
                    "wrapped" => false,
                ],
                self::MENU_INVENTORIES_IMPORTS => [
                    "label" => "Imports d'inventaires",
                    "right" => Action::SETTINGS_DISPLAY_INVENTORIES_IMPORT,
                    "save" => false,
                ],
            ],
        ],
        self::CATEGORY_TEMPLATES => [
            "label" => "Modèles de document",
            "icon" => "settings-document-template",
            "menus" => [
                self::MENU_TEMPLATE_DISPATCH => [
                    "label" => "Acheminements",
                    "right" => Action::SETTINGS_DISPLAY_DISPATCH_TEMPLATE,
                    "menus" => [
                        self::MENU_TEMPLATE_DISTPACH_WAYBILL => [
                            "label" => "Lettre de voiture",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_TEMPLATE_RECAP_WAYBILL => [
                            "label" => "Compte rendu",
                            "save" => true,
                            "discard" => true,
                        ],
                    ],
                ],
                self::MENU_TEMPLATE_DELIVERY => [
                    "label" => "Livraisons",
                    "right" => Action::SETTINGS_DISPLAY_DELIVERY_TEMPLATE,
                    "menus" => [
                        self::MENU_TEMPLATE_DELIVERY_WAYBILL => [
                            "label" => "Lettre de voiture",
                            "save" => true,
                            "discard" => true,
                        ],
                    ],
                ],
                self::MENU_TEMPLATE_SHIPPING => [
                    "label" => "Expéditions",
                    "right" => Action::SETTINGS_DISPLAY_SHIPPING_TEMPLATE,
                    "menus" => [
                        self::MENU_TEMPLATE_DELIVERY_SLIP => [
                            "label" => "Bordereau de livraison",
                            "save" => true,
                            "discard" => true,
                        ],
                    ],
                ],
            ],
        ],
    ];

    public const CATEGORY_GLOBAL = "global";
    public const CATEGORY_STOCK = "stock";
    public const CATEGORY_TRACING = "trace";
    public const CATEGORY_TRACKING = "track";
    public const CATEGORY_PRODUCTION = "production";
    public const CATEGORY_MOBILE = "mobile";
    public const CATEGORY_DASHBOARDS = "dashboards";
    public const CATEGORY_IOT = "iot";
    public const CATEGORY_NOTIFICATIONS = "notifications";
    public const CATEGORY_USERS = "utilisateurs";
    public const CATEGORY_DATA = "donnees";
    public const CATEGORY_TEMPLATES = "modeles";

    public const MENU_SITE_APPEARANCE = "apparence_site";
    public const MENU_WORKING_HOURS = "heures_travaillees";
    public const MENU_CLIENT = "client";
    public const MENU_OFF_DAYS = "jours_non_travailles";
    public const MENU_LABELS = "etiquettes";
    public const MENU_MAIL_SERVER = "serveur_email";

    public const MENU_CONFIGURATIONS = "configurations";
    public const MENU_VISIBILITY_GROUPS = "groupes_visibilite";
    public const MENU_ALERTS = "alertes";
    public const MENU_TOUCH_TERMINAL = "borne_tactile";

    public const MENU_COLLECT_REQUEST_AND_CREATE_REF = "demande_collecte_et_creation_reference";
    public const MENU_FAST_DELIVERY_REQUEST = "demande_livraison_rapide";
    public const MENU_INVENTORIES = "inventaires";
    public const MENU_FREQUENCIES = "frequences";
    public const MENU_INVENTORY_CONFIGURATION = "configuration";
    public const MENU_CATEGORIES = "categories";
    public const MENU_INVENTORY_PLANIFICATOR = "planificateur";
    public const MENU_ARTICLES = "articles";
    public const MENU_RECEPTIONS = "receptions";
    public const MENU_RECEPTIONS_STATUSES = "statuts_receptions";
    public const MENU_DISPUTE_STATUSES = "statuts_litiges";
    public const MENU_DISPUTE_TYPES = "types_litiges";
    public const MENU_REQUESTS = "demandes";

    public const MENU_DISPATCHES = "acheminements";
    public const MENU_STATUSES = "statuts";
    public const MENU_FIXED_FIELDS = "champs_fixes";
    public const MENU_RESERVES = "reserves";
    public const MENU_ARRIVALS = "arrivages";
    public const MENU_MOVEMENTS = "mouvements";
    public const MENU_FREE_FIELDS = "champs_libres";
    public const MENU_HANDLINGS = "services";
    public const MENU_REQUEST_TEMPLATES = "modeles_demande";
    public const MENU_TRUCK_ARRIVALS = "arrivages_camion";
    public const MENU_BR_ASSOCIATION = "association_BR";
    public const MENU_EMERGENCIES = "urgences";

    public const MENU_TRANSPORT_REQUESTS = "demande_transport";
    public const MENU_ROUNDS = "tournees";
    public const MENU_TEMPERATURES = "temperatures";

    public const MENU_DELIVERIES = "livraisons";
    public const MENU_DELIVERY_REQUEST_TEMPLATES = "modeles_demande_livraisons";
    public const MENU_DELIVERY_TYPES_FREE_FIELDS = "types_champs_libres_livraisons";
    public const MENU_COLLECTS = "collectes";
    public const MENU_SHIPPING = "expeditions";
    public const MENU_COLLECT_REQUEST_TEMPLATES = "modeles_demande_collectes";
    public const MENU_COLLECT_TYPES_FREE_FIELDS = "types_champs_libres_collectes";
    public const MENU_PURCHASE_STATUSES = "statuts_achats";
    public const MENU_PURCHASE_PLANIFICATION = "planification_achats";

    public const MENU_PREPARATIONS = "preparations";
    public const MENU_PREPARATIONS_DELIVERIES = "preparations_livraisons";
    public const MENU_VALIDATION = "validation";
    public const MENU_TRANSFERS = "transferts";

    public const MENU_FULL_SETTINGS = "parametrage_complet";

    public const MENU_TYPES_FREE_FIELDS = "types_champs_libres";

    public const MENU_PUSH_NOTIFICATIONS = "notifications_push";

    public const MENU_LANGUAGES = "langues";
    public const MENU_ROLES = "roles";
    public const MENU_USERS = "utilisateurs";
    public const MENU_SESSIONS = "licences";

    public const MENU_EXPORTS_ENCODING = "exports_encodage";
    public const MENU_CSV_EXPORTS = "exports_csv";
    public const MENU_IMPORTS = "imports";
    public const MENU_INVENTORIES_IMPORTS = "imports_inventaires";

    public const MENU_TEMPLATE_DISPATCH = "acheminement";
    public const MENU_TEMPLATE_DELIVERY = "livraison";
    public const MENU_TEMPLATE_SHIPPING = "expedition";
    public const MENU_TEMPLATE_DISTPACH_WAYBILL = "lettre_de_voiture";
    public const MENU_TEMPLATE_RECAP_WAYBILL = "compte_rendu";
    public const MENU_TEMPLATE_DELIVERY_WAYBILL = "lettre_de_voiture";
    public const MENU_TEMPLATE_DELIVERY_SLIP = "bordereau_de_livraison";

    public const MENU_NATIVE_COUNTRY = "pays_d_origine";
    public const MENU_NOMADE_RFID_CREATION = "creation_nomade_rfid";

    /**
     * @Route("/", name="settings_index")
     */
    public function index(): Response {
        return $this->render("settings/list.html.twig", [
            "settings" => self::SETTINGS,
        ]);
    }

    /**
     * @Route("/utilisateurs/langues", name="settings_language")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_LABELS_PERSO})
     */
    public function language(EntityManagerInterface $manager): Response {
        $translationRepository = $manager->getRepository(Translation::class);

        return $this->render("settings/utilisateurs/langues.html.twig", [
            'translations' => $translationRepository->findAll(),
            'menusTranslations' => array_column([], '1'),
        ]);
    }

    /**
     * @Route("/langues", name="settings_language_index")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_LABELS_PERSO})
     */
    public function languageIndex(EntityManagerInterface $entityManager,
                                  LanguageService        $languageService): Response {
        $languageRepository = $entityManager->getRepository(Language::class);
        $translationCategoryRepository = $entityManager->getRepository(TranslationCategory::class);

        $defaultLanguages = Stream::from($languageRepository->findBy(['selectable' => true]))
        ->map(fn(Language $language) => [
            'label' => $language->getLabel(),
            'value' => $language->getId(),
            'iconUrl' => $language->getFlag(),
            'checked' => $language->getSelected(),
        ])
        ->toArray();

        $languages = $languageService->getLanguages();

        $languages[] = [
            'label' => 'Ajouter une langue',
            'value' => 'NEW',
            'iconUrl' => '/svg/flags/Plus-flag.svg',
        ];

        $sidebar = [];
        $categories = $translationCategoryRepository->findBy(['type' => 'category']);
        foreach ($categories as $category) {
            $categoryLabel = $category->getLabel();
            $sidebar[$categoryLabel] = [];
            $menus = $translationCategoryRepository->findBy(['parent' => $category, 'type' => 'menu']);
            foreach ($menus as $menu) {
                $menuLabel = $menu->getLabel();
                $sidebar[$categoryLabel][] = $menuLabel;
            }
        }
        return $this->render("settings/utilisateurs/language/langues_index.html.twig", [
            'defaultLanguages' => $defaultLanguages,
            'languages' => $languages,
            'categories' => $sidebar,
        ]);
    }

    /**
     * @Route("/langues/api", name="settings_language_api" , methods={"GET"}, options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_LABELS_PERSO})
     */
    public function languageApi( Request $request, EntityManagerInterface $manager): Response {
        $data = $request->query;
        $languageRepository = $manager->getRepository(Language::class);
        $translationCategoryRepository = $manager->getRepository(TranslationCategory::class);

        $language = $data->get('language');
        if ($language === 'NEW') {
            $language = new Language();
            $language
                ->setFlag("data:image/svg+xml;charset=utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%3E%3C/svg%3E")
                ->setSelectable(true)
                ->setSlug(Language::NEW_SLUG);
        } else {
            $language = $languageRepository->findOneBy(['id' => $data->get('language')]);
        }

        $languageSlug = $language->getSlug();
        $defaultLanguage = array_key_exists($languageSlug, Language::DEFAULT_LANGUAGE_TRANSLATIONS )
            ? $languageRepository->findOneBy(['slug' => Language::DEFAULT_LANGUAGE_TRANSLATIONS[$languageSlug]])
            : $languageRepository->findOneBy(['selected' => true]) ;

        $translations = [];
        $categories = $translationCategoryRepository->findBy(['type' => 'category']);
        foreach ($categories as $category) {
            $categoryLabel = $category->getLabel();
            $translations[$categoryLabel] = ['subtitle'=> $category->getSubtitle()];
            $translations[$categoryLabel]["translations"] = $category->getTranslations($defaultLanguage->getSlug(), $language->getSlug());
            $menus = $translationCategoryRepository->findBy(['parent' => $category, 'type' => 'menu']);
            foreach ($menus as $menu) {
                $menuLabel = $menu->getLabel();
                $translations[$categoryLabel]['menus'][$menuLabel] = ['subtitle'=> $menu->getSubtitle()];
                $translations[$categoryLabel]['menus'][$menuLabel]["translations"] = $menu->getTranslations($defaultLanguage->getSlug(), $language->getSlug());
                $submenus = $translationCategoryRepository->findBy(['parent' => $menu, 'type' => 'submenu']);
                foreach ($submenus as $submenu){
                    $submenuLabel = $submenu->getLabel();
                    $translations[$categoryLabel]['menus'][$menuLabel]['submenus'][$submenuLabel] =['subtitle'=> $submenu->getSubtitle()];
                    $translations[$categoryLabel]['menus'][$menuLabel]['submenus'][$submenuLabel]["translations"] = $submenu->getTranslations($defaultLanguage->getSlug(), $language->getSlug());
                }
            }
        }

        return $this->json([
            'template' => $this->renderView("settings/utilisateurs/language/langues_settings.html.twig", [
                'defaultLanguage' => [
                    'label' => $defaultLanguage->getLabel(),
                    'flag' => $defaultLanguage->getFlag(),
                ],
                'language' => $language,
                'translations' => $translations,
            ]),
        ]);
    }

    /**
     * @Route("/langues/api/default", name="settings_default_language_api" , methods={"POST"}, options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_LABELS_PERSO})
     */
    public function defaultLanguageApi(Request $request, EntityManagerInterface $manager, CacheService $cacheService): Response {
        $data = $request->request;

        $languageRepository = $manager->getRepository(Language::class);
        $defaultLanguage = $languageRepository->find($data->get('language'));

        if($defaultLanguage->getSelectable()){
            foreach($languageRepository->findBy(['selected' => true]) as $language) {
                $language->setSelected(false);
            }

            $defaultLanguage->setSelected(true);
            $manager->flush();

            $cacheService->delete(CacheService::LANGUAGES);
            $cacheService->delete(CacheService::TRANSLATIONS);

            return $this->json([
                "success" => true,
            ]);
        }
        else {
            return $this->json([
                "success" => false,
                "message" => "La langue n'est pas sélectionnable",
            ]);
        }
    }

    /**
     * @Route("/langues/api/delete", name="settings_language_delete" , methods={"POST"}, options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_LABELS_PERSO})
     */
    public function deleteLanguageApi(EntityManagerInterface $manager,
                                      Request $request,
                                      CacheService $cacheService ): Response
    {
        $data = json_decode($request->getContent(), true);
        $languageRepository = $manager->getRepository(Language::class);
        $userRepository = $manager->getRepository(Utilisateur::class);
        $translationRepository = $manager->getRepository(Translation::class);
        $language = $languageRepository->find($data['language']);

        if (in_array($language->getSlug(),Language::NOT_DELETABLE_LANGUAGES)) {
            return $this->json([
                "success" => false,
                "message" => "Cette langue ne peut pas être supprimée",
            ]);
        }
        else {
            $translations = $translationRepository->findBy(['language' => $language]);
            foreach ($translations as $translation) {
                $manager->remove($translation);
            }

            $defaultLanguage = $languageRepository->findOneBy(['selected' => true]);
            foreach ($userRepository->findBy(['language' => $language]) as $user) {
                $user->setLanguage($defaultLanguage);
            }

            $manager->remove($language);
            $manager->flush();

            $cacheService->delete(CacheService::LANGUAGES);
            $cacheService->delete(CacheService::TRANSLATIONS);

            return $this->json([
                "success" => true,
                "msg" => "La langue <strong>{$language->getLabel()}</strong> a bien été supprimée.",
            ]);
        }
    }

    /**
     * @Route("/langues/api/save", name="settings_language_save_api" , methods={"POST"}, options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_LABELS_PERSO})
     */
    public function saveTranslationApi(EntityManagerInterface $manager,
                                       Request $request,
                                       AttachmentService $attachmentService,
                                       CacheService $cacheService ): Response {
        $data = $request->request;
        $file = $request->files;
        $languageRepository = $manager->getRepository(Language::class);
        $translationRepository = $manager->getRepository(Translation::class);
        $translationSourceRepository = $manager->getRepository(TranslationSource::class);

        $language = $data->get('language');
        if ($language === 'NEW') {
            $language = new Language;
            $flagCustom = $file->get('flagCustom');
            if ($flagCustom) {
                $flagFile = $attachmentService->createAttachments($file);
                $languageFile = $flagFile[0]->getFullPath();
            }
            else {
                $languageFile = $data->get('flagDefault');
            }

            $languageName = $data->get('languageName');
            $language
                ->setLabel($languageName)
                ->setFlag($languageFile)
                ->setSelectable(false)
                ->setSlug(strtolower(str_replace(' ', '_', $languageName)))
                ->setSelected(false);
            $manager->persist($language);
        } else {
            $language = $languageRepository->findOneBy(['id' => $data->get('language')]);
        }

        $translations = json_decode($data->get('translations'));

        foreach ($translations as $translation) {
           $id = $translation->id;
           $value = strip_tags($translation->value);
           $source = $translationSourceRepository->find($translation->source);
           if ($id != null or $id != '') {
               $translation= $translationRepository->find($id);
               if($value != null or $value != '') {
                   $translation->setTranslation($value);
               }
               else {
                   $manager->remove($translation);
               }
           }
           elseif ($value != null or $value != '') {
               $translation = new Translation();
               $translation
                   ->setTranslation($value)
                   ->setSource($source)
                   ->setLanguage($language);
                $manager->persist($translation);
           }
        }

        $manager->flush();

        $cacheService->delete(CacheService::LANGUAGES);
        $cacheService->delete(CacheService::TRANSLATIONS);

        return $this->json([
            "success" => true,
        ]);
    }


    /**
     * @Route("/afficher/{category}/{menu}/{submenu}", name="settings_item", options={"expose"=true})
     */
    public function item(EntityManagerInterface $entityManager,
                         string $category,
                         ?string $menu = null,
                         ?string $submenu = null): Response {
        if ($submenu) {
            $parent = self::SETTINGS[$category]["menus"][$menu] ?? null;
            $path = "settings/$category/$menu/";
        } else {
            $menu = $menu ?? array_key_first(self::SETTINGS[$category]["menus"]);

            // contains sub menus
            if (isset(self::SETTINGS[$category]["menus"][$menu]['menus'])) {
                $parent = self::SETTINGS[$category]["menus"][$menu] ?? null;
                $submenu = array_key_first($parent["menus"]);

                $path = "settings/$category/$menu/";
            } else {
                $parent = self::SETTINGS[$category] ?? null;
                $path = "settings/$category/";
            }
        }

        if (!$parent
            || isset($submenu) && !isset($parent['menus'][$submenu])) {
            throw new NotFoundHttpException('La page est introuvable');
        }

        return $this->render("settings/category.html.twig", [
            "category" => $category,
            "menu" => $menu,
            "submenu" => $submenu,

            "parent" => $parent,
            "selected" => $submenu ?? $menu,
            "path" => $path,
            "values" => $this->customValues($entityManager),
        ]);
    }

    private function smartWorkflowEndingMotives(SettingRepository $settingRepository): array {
        $smartItems = [];

        $items = explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES));
        $values = explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_COLLECT_WORKFLOW_ENDING_MOTIVE));

        foreach ($items as $item) {
            $smartItems[$item] = [
                "value" => $item,
                "label" => $item,
                "selected" => in_array($item, $values),
            ];
        }
        return $smartItems;
    }

    private function typeGenerator(string $category, $checkFirst = true): array {
        $typeRepository = $this->manager->getRepository(Type::class);
        $types = Stream::from($typeRepository->findByCategoryLabels([$category]))
            ->map(fn(Type $type) => [
                "label" => $type->getLabel(),
                "value" => $type->getId(),
                "iconUrl" => $type->getLogo()?->getFullPath(),
                "color" => $type->getColor(),
            ])
            ->toArray();

        if ($checkFirst && !empty($types)) {
            $types[0]["checked"] = true;
        }

        return $types;
    }

    public function customValues(EntityManagerInterface $entityManager): array {
        $temperatureRepository = $entityManager->getRepository(TemperatureRange::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationsRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $frequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
        $fixedFieldStandardRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);
        $subLineFieldParamRepository = $entityManager->getRepository(SubLineFixedField::class);
        $requestTemplateRepository = $entityManager->getRepository(RequestTemplate::class);
        $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $languageRepository = $entityManager->getRepository(Language::class);
        $nativeCountryRepository = $entityManager->getRepository(NativeCountry::class);
        $sessionHistoryRepository = $entityManager->getRepository(SessionHistoryRecord::class);
        $roleRepository = $entityManager->getRepository(Role::class);

        $categoryTypeArrivage = $entityManager->getRepository(CategoryType::class)->findBy(['label' => CategoryType::ARRIVAGE]);
        return [
            self::CATEGORY_GLOBAL => [
                self::MENU_CLIENT => fn() => [
                    "current_client" => $this->specificService->getAppClient(),
                ],
                self::MENU_LABELS => fn() => [
                    "typeOptions" => Stream::from($typeRepository->findBy(['category' => $categoryTypeArrivage]))
                        ->map(fn(Type $type) => [
                            "id" => $type->getId(),
                            "label" => $type->getLabel(),
                        ])
                        ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                        ->map(fn(array $n) => "<option value='{$n["id"]}'>{$n["label"]}</option>")
                        ->join(""),
                    "natureOptions" => Stream::from($natureRepository->findAll())
                        ->map(fn(Nature $nature) => [
                            "id" => $nature->getId(),
                            "label" => $nature->getCode(),
                        ])
                        ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                        ->map(fn(array $n) => "<option value='{$n["id"]}'>{$n["label"]}</option>")
                        ->join(""),
                ],
            ],
            self::CATEGORY_STOCK => [
                self::MENU_ARTICLES => [
                    self::MENU_LABELS => fn() => [
                        "free_fields" => Stream::from($freeFieldRepository->findByCategory(CategorieCL::ARTICLE))
                            ->keymap(fn(FreeField $field) => [$field->getLabel(), $field->getLabel()])
                            ->toArray(),
                    ],
                    self::MENU_TYPES_FREE_FIELDS => function() use ($entityManager, $typeRepository) {
                        $categoryType = CategoryType::ARTICLE;
                        $types = Stream::from($typeRepository->findByCategoryLabels([$categoryType]))
                            ->map(fn(Type $type) => [
                                "label" => $type->getLabel(),
                                "value" => $type->getId(),
                            ])
                            ->toArray();

                        $types[0]["checked"] = true;

                        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);
                        $categories = Stream::from($categorieCLRepository->findByLabel([
                            CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE,
                        ]))
                            ->map(fn(CategorieCL $category) => "<option value='{$category->getId()}'>{$category->getLabel()}</option>")
                            ->join("");

                        return [
                            "types" => $types,
                            "category" => $categoryType,
                            "categories" => "<select name='category' class='form-control data'>$categories</select>",
                        ];
                    },
                    self::MENU_NATIVE_COUNTRY => fn() => [
                        "native_countries" => Stream::from($nativeCountryRepository->findAll())
                            ->map(fn(NativeCountry $nativeCountry) => [
                                "code" => $nativeCountry->getCode(),
                                "label" => $nativeCountry->getLabel(),
                                "active" => $nativeCountry->isActive(),
                            ])
                            ->toArray(),
                    ],
                ],
                self::MENU_REQUESTS => [
                    self::MENU_DELIVERIES => fn() => [
                        "deliveryRequestBehavior" => $settingRepository->findOneBy([
                            'label' => [Setting::DIRECT_DELIVERY, Setting::CREATE_PREPA_AFTER_DL, Setting::CREATE_DELIVERY_ONLY],
                            'value' => 1,
                        ])?->getLabel(),
                    ],
                    self::MENU_DELIVERY_REQUEST_TEMPLATES => function() use ($requestTemplateRepository, $typeRepository) {
                        return $this->getRequestTemplates($typeRepository, $requestTemplateRepository, Type::LABEL_DELIVERY);
                    },
                    self::MENU_COLLECT_REQUEST_TEMPLATES => function() use ($requestTemplateRepository, $typeRepository) {
                        return $this->getRequestTemplates($typeRepository, $requestTemplateRepository, Type::LABEL_COLLECT);
                    },
                    self::MENU_DELIVERY_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_LIVRAISON),
                        'category' => CategoryType::DEMANDE_LIVRAISON,
                    ],
                    self::MENU_FIXED_FIELDS => function() use ($typeRepository, $fixedFieldStandardRepository, $userRepository) {
                        $receiver = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::FIELD_CODE_RECEIVER_DEMANDE);
                        $defaultType = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::FIELD_CODE_TYPE_DEMANDE);
                        $defaultLocationByType = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::FIELD_CODE_DESTINATION_DEMANDE);
                        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

                        return [
                            "receiver" => [
                                "field" => $receiver->getId(),
                                "elementsType" => $receiver->getElementsType(),
                                "elements" => Stream::from($receiver->getElements() ?? [])
                                    ->map(function(string $element) use ($userRepository) {
                                        $user = $userRepository->find($element);
                                        return [
                                            "label" => $user->getUsername(),
                                            "value" => $user->getId(),
                                            "selected" => true,
                                        ];
                                    })
                                    ->toArray(),
                            ],
                            "type" => [
                                "field" => $defaultType->getId(),
                                "elementsType" => $defaultType->getElementsType(),
                                "elements" => Stream::from($types)
                                    ->map(function(Type $type) use ($defaultType, $typeRepository) {
                                        $selectedType = !empty($defaultType->getElements()) ? $typeRepository->find($defaultType->getElements()[0]) : null;
                                        return [
                                            "label" => $type->getLabel(),
                                            "value" => $type->getId(),
                                            "selected" => $selectedType && $selectedType->getId() === $type->getId(),
                                        ];
                                    })
                                    ->toArray(),
                            ],
                            "locationByType" => [
                                "field" => $defaultLocationByType?->getId(),
                                "elementsType" => $defaultLocationByType?->getElementsType(),
                                "elements" => json_encode($this->settingsService->getDefaultDeliveryLocationsByType($this->manager)),
                            ],
                            "deliveryTypesCount" => $typeRepository->countAvailableForSelect(CategoryType::DEMANDE_LIVRAISON, []),
                        ];
                    },
                    self::MENU_COLLECT_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_COLLECTE),
                        'category' => CategoryType::DEMANDE_COLLECTE,
                    ],
                    self::MENU_PURCHASE_STATUSES => fn() => [
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_PURCHASE_REQUEST),
                    ],
                    self::MENU_SHIPPING => function() use ($settingRepository, $roleRepository) {
                        $toTreatRoleIds = $settingRepository->getOneParamByLabel(Setting::SHIPPING_TO_TREAT_SEND_TO_ROLES)
                            ? explode(',', $settingRepository->getOneParamByLabel(Setting::SHIPPING_TO_TREAT_SEND_TO_ROLES))
                            : null;
                        $shippedRoleIds = $settingRepository->getOneParamByLabel(Setting::SHIPPING_SHIPPED_SEND_TO_ROLES)
                            ? explode(',', $settingRepository->getOneParamByLabel(Setting::SHIPPING_SHIPPED_SEND_TO_ROLES))
                            : null;

                        return [
                            'toTreatRoles' => $toTreatRoleIds ? Stream::from($roleRepository->findBy(['id' => $toTreatRoleIds]))
                                ->map(fn(Role $role) => [
                                    'label' => $role->getLabel(),
                                    'value' => $role->getId(),
                                    'selected' => true
                                ]) : [],
                            'shippedRoles' => $shippedRoleIds ? Stream::from($roleRepository->findBy(['id' => $shippedRoleIds]))
                                ->map(fn(Role $role) => [
                                    'label' => $role->getLabel(),
                                    'value' => $role->getId(),
                                    'selected' => true
                                ]) : [],
                        ];
                    },
                ],
                self::MENU_INVENTORIES => [
                    self::MENU_CATEGORIES => fn() => [
                        "frequencyOptions" => Stream::from($frequencyRepository->findAll())
                            ->map(fn(InventoryFrequency $freq) => [
                                "id" => $freq->getId(),
                                "label" => $freq->getLabel(),
                            ])
                            ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                            ->map(fn(array $n) => "<option value='{$n["id"]}'>{$n["label"]}</option>")
                            ->prepend("<option disabled selected>Sélectionnez une fréquence</option>")
                            ->join(""),
                    ],
                ],
                self::MENU_RECEPTIONS => [
                    self::MENU_RECEPTIONS_STATUSES => fn() => [
                        "receptionStatuses" => $statusRepository->findByCategorieName(CategorieStatut::RECEPTION, 'displayOrder'),
                    ],
                    self::MENU_DISPUTE_STATUSES => fn() => [
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_RECEPTION_DISPUTE),
                    ],
                    self::MENU_FREE_FIELDS => fn() => [
                        "type" => $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::RECEPTION, Type::LABEL_RECEPTION),
                    ],
                ],
                self::MENU_TOUCH_TERMINAL => [
                    self::MENU_COLLECT_REQUEST_AND_CREATE_REF => fn() => [
                        'alreadyUnlinked' => empty($entityManager->getRepository(KioskToken::class)->findAll()),
                    ],
                    self::MENU_FAST_DELIVERY_REQUEST => fn() => [
                        'filterFields' => Stream::from($entityManager->getRepository(FreeField::class)->findByCategory(CategorieCL::REFERENCE_ARTICLE))
                            ->map(static fn(FreeField $freeField) => [
                                'label' => $freeField->getLabel(),
                                'value' => $freeField->getId(),
                            ])
                            ->concat(DeliveryStationLine::REFERENCE_FIXED_FIELDS)
                            ->toArray(),
                        'deliveryStationLine' => new DeliveryStationLine(),
                    ],
                ]
            ],
            self::CATEGORY_TRACING => [
                self::MENU_DISPATCHES => [
                    self::MENU_CONFIGURATIONS => fn() => [
                        "referenceTypes" => Stream::from($typeRepository->findByCategoryLabels([CategoryType::ARTICLE]))
                            ->map(fn(Type $type) => [
                                "value" => $type->getId(),
                                "label" => $type->getLabel(),
                            ])->toArray(),
                        "automaticallyCreateMovementOnValidationTypes" => json_encode($this->settingsService->getSelectOptionsBySetting($this->manager, Setting::AUTOMATICALLY_CREATE_MOVEMENT_ON_VALIDATION_TYPES)),
                        "autoUngroupTypes" => json_encode($this->settingsService->getSelectOptionsBySetting($this->manager, Setting::AUTO_UNGROUP_TYPES)),
                        "dispatchFixedFieldsFilterable" => Stream::from($fixedFieldByTypeRepository->findBy(['entityCode'=> FixedFieldStandard::ENTITY_CODE_DISPATCH]))
                            ->filter(static fn(FixedFieldByType $fixedField) => in_array($fixedField->getFieldCode(), FixedField::FILTERED_FIELDS[FixedFieldStandard::ENTITY_CODE_DISPATCH]))
                            ->toArray(),
                    ],
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldStandardRepository, $subLineFieldParamRepository, $fixedFieldByTypeRepository) {
                        $emergencyField = $fixedFieldByTypeRepository->findOneBy(['entityCode' => FixedFieldStandard::ENTITY_CODE_DISPATCH, 'fieldCode' => FixedFieldStandard::FIELD_CODE_EMERGENCY]);
                        $businessField = $fixedFieldByTypeRepository->findOneBy(['entityCode' => FixedFieldStandard::ENTITY_CODE_DISPATCH, 'fieldCode' => FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT]);

                        $dispatchLogisticUnitLengthField = $subLineFieldParamRepository->findOneBy([
                            'entityCode' => SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT,
                            'fieldCode' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH,
                        ]);
                        $dispatchLogisticUnitWidthField = $subLineFieldParamRepository->findOneBy([
                            'entityCode' => SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT,
                            'fieldCode' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH,
                        ]);
                        $dispatchLogisticUnitHeightField = $subLineFieldParamRepository->findOneBy([
                            'entityCode' => SubLineFixedField::ENTITY_CODE_DISPATCH_LOGISTIC_UNIT,
                            'fieldCode' => SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT,
                        ]);
                        return [
                            'types' => $this->typeGenerator(CategoryType::DEMANDE_DISPATCH),
                            "emergency" => [
                                "field" => $emergencyField->getId(),
                                "elementsType" => $emergencyField->getElementsType(),
                                "elements" => Stream::from($emergencyField->getElements())
                                    ->map(fn(string $element) => [
                                        "label" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                            "businessUnit" => [
                                "field" => $businessField->getId(),
                                "elementsType" => $businessField->getElementsType(),
                                "elements" => Stream::from($businessField->getElements())
                                    ->map(fn(string $element) => [
                                        "label" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                            "dispatchLogisticUnitFixedFields" => [
                                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LENGTH => [
                                    "field" => $dispatchLogisticUnitLengthField->getId(),
                                    "elementsType" => FixedFieldStandard::ELEMENTS_TYPE_FREE,
                                    "elements" => Stream::from($dispatchLogisticUnitLengthField->getElements() ?? [])
                                        ->map(fn(string $element) => [
                                            "label" => $element,
                                            "value" => $element,
                                            "selected" => true,
                                        ])
                                        ->toArray(),
                                ],
                                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WIDTH => [
                                    "field" => $dispatchLogisticUnitWidthField->getId(),
                                    "elementsType" => FixedFieldStandard::ELEMENTS_TYPE_FREE,
                                    "elements" => Stream::from($dispatchLogisticUnitWidthField->getElements() ?? [])
                                        ->map(fn(string $element) => [
                                            "label" => $element,
                                            "value" => $element,
                                            "selected" => true,
                                        ])
                                        ->toArray(),
                                ],
                                SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_HEIGHT => [
                                    "field" => $dispatchLogisticUnitHeightField->getId(),
                                    "elementsType" => FixedFieldStandard::ELEMENTS_TYPE_FREE,
                                    "elements" => Stream::from($dispatchLogisticUnitHeightField->getElements() ?? [])
                                        ->map(fn(string $element) => [
                                            "label" => $element,
                                            "value" => $element,
                                            "selected" => true,
                                        ])
                                        ->toArray(),
                                ],
                            ]
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_DISPATCH),
                        'category' => CategoryType::DEMANDE_DISPATCH,
                    ],
                    self::MENU_STATUSES => function() {
                        $types = $this->typeGenerator(CategoryType::DEMANDE_DISPATCH, false);
                        $types[0]["checked"] = true;

                        return [
                            'types' => $types,
                            'categoryType' => CategoryType::DEMANDE_DISPATCH,
                            'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_DISPATCH),
                            'groupedSignatureTypes' => $this->dispatchService->getGroupedSignatureTypes(),
                        ];
                    },
                ],
                self::MENU_ARRIVALS => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldStandardRepository) {
                        $field = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_ARRIVAGE, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT);

                        return [
                            "businessUnit" => [
                                "field" => $field->getId(),
                                "elementsType" => $field->getElementsType(),
                                "elements" => Stream::from($field->getElements())
                                    ->map(fn(string $element) => [
                                        "label" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::ARRIVAGE),
                        'category' => CategoryType::ARRIVAGE,
                    ],
                    self::MENU_DISPUTE_STATUSES => fn() => [
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_ARRIVAL_DISPUTE),
                    ],
                    self::MENU_STATUSES => function() {
                        $types = $this->typeGenerator(CategoryType::ARRIVAGE, false);
                        $types[0]["checked"] = true;

                        return [
                            'types' => $types,
                            'categoryType' => CategoryType::ARRIVAGE,
                            'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_ARRIVAL),
                        ];
                    },
                ],
                self::MENU_HANDLINGS => [
                    self::MENU_FIXED_FIELDS => function() use ($userRepository, $typeRepository, $fixedFieldStandardRepository) {
                        $field = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_HANDLING, FixedFieldStandard::FIELD_CODE_EMERGENCY);
                        $receiversField = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_HANDLING, FixedFieldStandard::FIELD_CODE_RECEIVERS_HANDLING);
                        $types = $this->typeGenerator(CategoryType::DEMANDE_HANDLING, false);
                        return [
                            "emergency" => [
                                "field" => $field->getId(),
                                "elementsType" => $field->getElementsType(),
                                "elements" => Stream::from($field->getElements())
                                    ->map(fn(string $element) => [
                                        "label" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                            "receivers" => [
                                "field" => $receiversField->getId(),
                                "elementsType" => $receiversField->getElementsType(),
                                "types" => $types,
                                "elements" => Stream::from($receiversField->getElements() ?? [])
                                    ->map(fn($users, $type) => [
                                        "type" => Stream::from([$type])->map(fn($typeId) => [
                                            "value" => $typeId,
                                            "label" => $typeRepository->find($typeId)->getLabel(),
                                            ])
                                            ->toArray(),
                                        "users" => Stream::from($users)->map(fn($user) => [
                                            "value" => $user,
                                            "label" => $userRepository->find($user)->getUsername(),
                                            'selected' => true,
                                            ])
                                            ->toArray(),
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_HANDLING),
                        'category' => CategoryType::DEMANDE_HANDLING,
                    ],
                    self::MENU_REQUEST_TEMPLATES => function() use ($requestTemplateRepository, $typeRepository) {
                        return $this->getRequestTemplates($typeRepository, $requestTemplateRepository, Type::LABEL_HANDLING);
                    },
                    self::MENU_STATUSES => function() {
                        $types = $this->typeGenerator(CategoryType::DEMANDE_HANDLING, false);
                        $types[0]["checked"] = true;

                        return [
                            'types' => $types,
                            'categoryType' => CategoryType::DEMANDE_HANDLING,
                            'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_HANDLING),
                        ];
                    },
                ],
                self::MENU_MOVEMENTS => [
                    self::MENU_FREE_FIELDS => fn() => [
                        "type" => $typeRepository->findOneByLabel(Type::LABEL_MVT_TRACA),
                    ],
                ],
                self::MENU_EMERGENCIES => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldStandardRepository) {
                        $emergencyTypeField = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_EMERGENCY, FixedFieldStandard::FIELD_CODE_EMERGENCY_TYPE);
                        return [
                            "emergencyType" => [
                                "field" => $emergencyTypeField->getId(),
                                "elementsType" => $emergencyTypeField->getElementsType(),
                                "elements" => Stream::from($emergencyTypeField->getElements() ?? [])
                                    ->map(fn(string $element) => [
                                        "label" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                ],
            ],
            self::CATEGORY_PRODUCTION => [
                self::MENU_FULL_SETTINGS => [
                    self::MENU_CONFIGURATIONS => function() use ($settingRepository, $userRepository) {
                        $notificationEmailUsers = $settingRepository->getOneParamByLabel(Setting::SENDING_EMAIL_EVERY_STATUS_CHANGE_IF_EMERGENCY_USERS);
                        $users = $notificationEmailUsers
                            ? $userRepository->findBy(["id" => explode(",", $notificationEmailUsers)])
                            : [];

                        return [
                            "notificationEmailUsers" => Stream::from($users)
                                ->map(static fn(Utilisateur $user) => [
                                    "label" => $user->getUsername(),
                                    "value" => $user->getId(),
                                    "selected" => true,
                                ])
                                ->toArray(),
                        ];
                    },
                    self::MENU_STATUSES => function() {
                        $types = $this->typeGenerator(CategoryType::PRODUCTION, false);
                        $types[0]["checked"] = true;

                        return [
                            'types' => $types,
                            'categoryType' => CategoryType::PRODUCTION,
                            'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_PRODUCTION),
                        ];
                    },
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldStandardRepository) {
                        $field = $fixedFieldStandardRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_PRODUCTION, FixedFieldStandard::FIELD_CODE_EMERGENCY);

                        return [
                            "emergency" => [
                                "field" => $field->getId(),
                                "elementsType" => $field->getElementsType(),
                                "elements" => Stream::from($field->getElements())
                                    ->map(fn(string $element) => [
                                        "label" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::PRODUCTION),
                        'category' => CategoryType::PRODUCTION,
                    ],
                ],
            ],
            self::CATEGORY_TRACKING => [
                self::MENU_ROUNDS => fn() => [
                    "packRejectMotives" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_PACK_REJECT_MOTIVES)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $value,
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                    "deliveryRejectMotives" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_DELIVERY_REJECT_MOTIVES)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $value,
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                    "collectRejectMotives" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_COLLECT_REJECT_MOTIVES)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $value,
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                    "collectWorkflowEndingMotives" => $this->smartWorkflowEndingMotives($settingRepository),
                    "transportRoundEndLocations" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_END_ROUND_LOCATIONS)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $locationsRepository->find($value)->getLabel(),
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                    "transportRoundCollectedPacksLocations" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_COLLECTED_PACKS_LOCATIONS)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $locationsRepository->find($value)->getLabel(),
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                    "transportRoundRejectedPacksLocations" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_REJECTED_PACKS_LOCATIONS)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $locationsRepository->find($value)->getLabel(),
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                    "transportRoundNeededNaturesToDrop" =>
                        Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_ROUND_NEEDED_NATURES_TO_DROP)))
                            ->filter(fn(string $value) => $value)
                            ->keymap(fn(string $value) => [
                                $value, [
                                    "value" => $value,
                                    "label" => $this->getFormatter()->nature($natureRepository->find($value)),
                                    "selected" => true,
                                ],
                            ])
                            ->toArray(),
                ],
                self::MENU_TEMPERATURES => fn() => [
                    "temperatureRanges" => Stream::from($temperatureRepository->findAll())
                        ->map(fn(TemperatureRange $range) => [
                            "label" => $range->getValue(),
                            "value" => $range->getValue(),
                            "selected" => true,
                            "class" => !($range->getLocations()->isEmpty() && $range->getNatures()->isEmpty() && $range->getTransportDeliveryRequestNatures()->isEmpty())
                                ? 'no-deletable'
                                : 'deletable',
                        ])
                        ->toArray(),
                ],
                self::MENU_TRANSPORT_REQUESTS => [
                    self::MENU_CONFIGURATIONS => fn() => [
                        "transportDeliveryRequestEmergencies" =>
                            Stream::from(explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_DELIVERY_REQUEST_EMERGENCIES)))
                                ->filter(fn(string $value) => $value)
                                ->keymap(fn(string $value) => [
                                    $value, [
                                        "value" => $value,
                                        "label" => $value,
                                        "selected" => true,
                                    ],
                                ])
                                ->toArray(),
                        "receiversEmails" =>
                            Stream::from($userRepository->findBy(['id' => explode(',', $settingRepository->getOneParamByLabel(Setting::TRANSPORT_DELIVERY_DESTINATAIRES_MAIL))]))
                                ->map(fn(Utilisateur $user) => [
                                    "value" => $user->getId(),
                                    "label" => $user->getUsername(),
                                    "selected" => true,
                                ])
                                ->toArray(),
                    ],
                    self::MENU_DELIVERY_TYPES_FREE_FIELDS => fn() => [
                        "types" => $this->typeGenerator(CategoryType::DELIVERY_TRANSPORT),
                        'category' => CategoryType::DELIVERY_TRANSPORT,
                    ],
                    self::MENU_COLLECT_TYPES_FREE_FIELDS => fn() => [
                        "types" => $this->typeGenerator(CategoryType::COLLECT_TRANSPORT),
                        'category' => CategoryType::COLLECT_TRANSPORT,
                    ],
                ],
            ],
            self::CATEGORY_IOT => [
                self::MENU_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                    $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::SENSOR]))
                        ->map(fn(Type $type) => [
                            "label" => $type->getLabel(),
                            "value" => $type->getId(),
                        ])
                        ->toArray();

                    $types[0]["checked"] = true;

                    return [
                        "types" => $types,
                    ];
                },
            ],
            self::CATEGORY_DATA => [
                self::MENU_CSV_EXPORTS => fn() => [
                    "statuts" => $statusRepository->findByCategorieName(CategorieStatut::EXPORT),
                ],
                self::MENU_IMPORTS => function () use ($typeRepository, $statusRepository) {
                    $statuses = $statusRepository->findByCategoryNameAndStatusCodes(
                        CategorieStatut::IMPORT,
                        [Import::STATUS_UPCOMING, Import::STATUS_SCHEDULED, Import::STATUS_IN_PROGRESS, Import::STATUS_CANCELLED, Import::STATUS_FINISHED]
                    );
                    $types = $typeRepository->findByCategoryLabels([CategoryType::IMPORT]);
                    return [
                        "statuts" => $statuses,
                        "types" => $types,
                    ];
                },
            ],
            self::CATEGORY_NOTIFICATIONS => [
                self::MENU_ALERTS => function() use ($alertTemplateRepository) {
                    return $this->getAlertTemplates($alertTemplateRepository);
                },
            ],
            self::CATEGORY_USERS => [
                self::MENU_USERS => fn() => [
                    "newUser" => new Utilisateur(),
                    "newUserLanguage" => $this->languageService->getNewUserLanguage($entityManager),
                    "languages" => Stream::from($languageRepository->findby(['hidden' => false]))
                        ->map(fn(Language $language) => [
                            "value" => $language->getId(),
                            "label" => $language->getLabel(),
                            "icon" => $language->getFlag(),
                        ])
                        ->toArray(),
                    "dateFormats" => Stream::from(Language::DATE_FORMATS)
                        ->map(fn($format, $key) => [
                            "label" => $key,
                            "value" => $format,
                        ])
                        ->toArray(),
                    "dispatchBusinessUnits" => $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT),
                ],
                self::MENU_SESSIONS => fn() => [
                    "activeSessionsCount" => $sessionHistoryRepository->countOpenedSessions(),
                    "maxAuthorizedSessions" => $this->sessionHistoryRecordService->getOpenedSessionLimit(),
                ],
            ],
        ];
    }

    /**
     * @Route("/enregistrer", name="settings_save", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function save(Request $request): Response {
        try {
            $result = $this->service->save($request);
        } catch (RuntimeException $exception) {
            return $this->json([
                "success" => false,
                "msg" => $exception->getMessage(),
            ]);
        }

        return $this->json(array_merge(
            [
                "success" => true,
                "msg" => "Les nouveaux paramétrages ont été enregistrés",
            ],
            $result ?? [],
        ));
    }

    #[Route("/enregistrer/champ-fixe/{field}", name: "settings_save_field_param", options: ["expose" => true], methods: ["POST"])]
    #[HasPermission([Menu::PARAM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function saveFieldParam(Request $request, EntityManagerInterface $manager, int $field): Response {
        $entity = match($request->request->get('fixedFieldType')) {
            FixedFieldByType::FIELD_TYPE => FixedFieldByType::class,
            SubLineFixedField::FIELD_TYPE => SubLineFixedField::class,
            default => FixedFieldStandard::class
        };

        $field = $manager->find($entity, $field);

        if ($field->getElementsType() == FixedFieldStandard::ELEMENTS_TYPE_FREE) {
            $field->setElements(explode(",", $request->request->get("elements")));
        } else if ($field->getElementsType() == FixedFieldStandard::ELEMENTS_TYPE_FREE_NUMBER) {
            $elements = $request->request->get("elements");

            if($elements !== "" && !StringHelper::matchEvery(explode(",", $elements), StringHelper::INTEGER_AND_DECIMAL_REGEX)) {
                throw new FormException("Une ou plusieurs valeurs renseignées ne sont pas valides (entiers et décimaux uniquement).");
            } else {
                $field->setElements(explode(",", $elements));
            }
        } elseif ($field->getElementsType() == FixedFieldStandard::ELEMENTS_TYPE_USER) {
            $lines = $request->request->has("lines") ? json_decode($request->request->get("lines"), true) : [];
            $elements = [];
            foreach ($lines as $line) {
                $elements[$line['handlingType']] = $line['user'];
            }
            $field->setElements($elements);
        } else if($field->getElementsType() == FixedFieldStandard::ELEMENTS_RECEIVER) {
            $settingRepository = $manager->getRepository(Setting::class);
            $setting = $settingRepository->findOneBy(['label' => Setting::RECEIVER_EQUALS_REQUESTER]);
            if($request->request->get("defaultReceiver")){
                $field->setElements([$request->request->get("defaultReceiver")]);
            } else {
                $field->setElements([]);
            }

            if($request->request->has(Setting::RECEIVER_EQUALS_REQUESTER)){
                $setting->setValue($request->request->get(Setting::RECEIVER_EQUALS_REQUESTER));
            }
        } else if($field->getElementsType() == FixedFieldStandard::ELEMENTS_TYPE) {
            if($request->request->get("demandeType")){
                $field->setElements([$request->request->get("demandeType")]);
            } else {
                $field->setElements([]);
            }
        }
        else if($field->getElementsType() == FixedFieldStandard::ELEMENTS_LOCATION_BY_TYPE){
            if($request->request->has('deliveryType') && $request->request->has('deliveryRequestLocation')){
                $deliveryTypes = explode(',', $request->request->get("deliveryType"));
                $deliveryRequestLocations = explode(',', $request->request->get("deliveryRequestLocation"));

                if(count($deliveryTypes) !== count($deliveryRequestLocations)){
                    return $this->json([
                        "success" => false,
                        "msg" => "Une configuration d'emplacement de livraison par défaut est invalide",
                    ]);
                }

                $associatedTypesAndLocations = array_combine($deliveryTypes, $deliveryRequestLocations);
                $invalidDeliveryTypes = (
                    empty($associatedTypesAndLocations)
                    || !Stream::from($associatedTypesAndLocations)
                        ->filter(fn(string $key, string $value) => !$key || !$value)
                        ->isEmpty()
                );
                if ($invalidDeliveryTypes) {
                    throw new RuntimeException("Une configuration d'emplacement de livraison par défaut est invalide");
                }
                $field->setElements($associatedTypesAndLocations);
            } else {
                return $this->json([
                    "success" => false,
                    "msg" => "Une configuration d'emplacement de livraison par défaut est invalide",
                ]);
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Les nouveaux paramétrages du champ ont été enregistrés",
        ]);
    }

    /**
     * @Route("/heures-travaillees-api", name="settings_working_hours_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_WORKING_HOURS})
     */
    public function workingHoursApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $daysWorkedRepository = $manager->getRepository(DaysWorked::class);

        $data = [];
        foreach ($daysWorkedRepository->findAll() as $day) {
            if ($edit) {
                $worked = $day->isWorked() ? "checked" : "";
                $data[] = [
                    "day" => "{$day->getDisplayDay()} <input type='hidden' name='id' class='$class' value='{$day->getId()}'/>",
                    "hours" => "<input name='hours' class='$class' data-global-error='Quantité' value='{$day->getTimes()}'/>",
                    "worked" => "<div class='checkbox-container'><input type='checkbox' name='worked' class='$class' $worked/></div>",
                ];
            } else {
                $data[] = [
                    "day" => $day->getDisplayDay(),
                    "hours" => $day->getTimes(),
                    "worked" => $day->isWorked() ? "Oui" : "Non",
                ];
            }
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/creneaux-horaires-api", name="settings_hour_shift_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_WORKING_HOURS})
     */
    public function timeSlotsApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $hourShiftsRepository = $manager->getRepository(CollectTimeSlot::class);

        $data = [];
        /**
         * @var CollectTimeSlot $shift
         */
        foreach ($hourShiftsRepository->findAll() as $shift) {
            $hours = $shift->getStart() . '-' . $shift->getEnd();
            if ($edit) {
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row' data-id='{$shift->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    " : "",
                    "name" => "<input name='id' type='hidden' class='$class' value='{$shift->getId()}'><input name='name' class='$class' data-global-error='Nom du créneau' value='{$shift->getName()}'/>",
                    "hours" => "<input name='hours' class='$class' data-global-error='Heures' value='{$hours}'/>",
                ];
            } else {
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row-view' data-type='timeSlots' data-id='{$shift->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    " : "",
                    "name" => $shift->getName(),
                    "hours" => $hours,
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "id" => "",
            "name" => "",
            "hours" => "",
        ];

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/heures-depart-api", name="settings_starting_hours_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_ROUND})
     */
    public function startingHoursApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $hourShiftsRepository = $manager->getRepository(TransportRoundStartingHour::class);

        $data = [];
        foreach ($hourShiftsRepository->findAll() as $shift) {
            $hour = $shift->getHour();
            $deliverers = "<select name='deliverers' required data-s2='user' data-parent='body' class='$class' data-global-error='Livreur(s)' multiple='multiple'/>";
            foreach ($shift->getDeliverers() as $deliverer) {
                $id = $deliverer->getId();
                $name = $deliverer->getUsername();
                $deliverers .= "<option value='$id' selected>$name</option>";
            }
            $deliverers .= "</select>";

            if ($edit) {
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row' data-id='{$shift->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    " : "",
                    "id" => $shift->getId(),
                    "hour" => "<input name='hour' class='$class' data-global-error='Heure' value='{$hour}'/>",
                    "deliverers" => $deliverers,
                ];
            } else {
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row-view' data-type='startingHours' data-id='{$shift->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    " : "",
                    "id" => $shift->getId(),
                    "hour" => $hour,
                    "deliverers" => Stream::from($shift->getDeliverers())
                        ->map(fn(Utilisateur $utilisateur) => $utilisateur->getUsername())
                        ->join(','),
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "id" => "",
            "hour" => "",
            "deliverers" => "",
        ];

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/jours-non-travailles-api", name="settings_off_days_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_NOT_WORKING_DAYS})
     */
    public function offDaysApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $data = [];
        if (!$edit) {
            $workFreeDayRepository = $manager->getRepository(WorkFreeDay::class);

            foreach ($workFreeDayRepository->findAll() as $day) {
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row' data-id='{$day->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    " : "",
                    "day" => "<span data-timestamp='{$day->getTimestamp()}'>" . FormatHelper::longDate($day->getDay()) . "</span>",
                ];
            }
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/jours-non-travailles/supprimer/{entity}", name="settings_off_days_delete", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteOffDay(EntityManagerInterface $manager, WorkFreeDay $entity) {
        $manager->remove($entity);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le jour non travaillé a été supprimé",
        ]);
    }

    /**
     * @Route("/champs-libres/header/{type}", name="settings_type_header", options={"expose"=true})
     */
    public function typeHeader(Request $request,
                               FormService $formService,
                               ?Type $type = null): Response {
        $categoryTypeRepository = $this->manager->getRepository(CategoryType::class);

        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $categoryLabel = $request->query->get("category");
        $category = in_array($categoryLabel, CategoryType::ALL)
            ? $categoryTypeRepository->findOneBy(['label' => $categoryLabel])
            : null;

        if (!$category) {
            return $this->json([
                "success" => false,
                "msg" => "Configuration invalide, les types ne peuvent pas être récupérés",
            ]);
        }

        if ($edit) {
            $fixedFieldRepository = $this->manager->getRepository(FixedFieldStandard::class);

            $label = $type?->getLabel();
            $description = $type?->getDescription();
            $color = $type?->getColor() ?: "#3353D7";
            $label = htmlspecialchars($label);
            $description = htmlspecialchars($description);

            $data = [
                [
                    "type" => "hidden",
                    "name" => "entity",
                    "class" => "category",
                    "value" => $categoryLabel,
                ],
                [
                    "label" => "Libellé*",
                    "value" => "<input name='label' class='data form-control' required value=\"$label\">",
                ],
                [
                    "label" => "Description",
                    "value" => "<input name='description' class='data form-control' value=\"$description\">",
                ],
            ];

            if (in_array($categoryLabel, [CategoryType::ARTICLE, CategoryType::DEMANDE_DISPATCH, CategoryType::PRODUCTION])) {
                $inputId = rand(0, 1000000);

                $data[] = [
                    "label" => "Couleur",
                    "value" => "
                    <input type='color' class='form-control wii-color-picker data' name='color' value='$color' list='type-color-$inputId'/>
                    <datalist id='type-color-$inputId'>
                        <option>#D76433</option>
                        <option>#D7B633</option>
                        <option>#A5D733</option>
                        <option>#33D7D1</option>
                        <option>#33A5D7</option>
                        <option>#3353D7</option>
                        <option>#6433D7</option>
                        <option>#D73353</option>
                    </datalist>",
                ];
            }

            if (in_array($categoryLabel, [CategoryType::DEMANDE_LIVRAISON, CategoryType::DEMANDE_COLLECTE])) {
                $notificationsEnabled = $type && $type->isNotificationsEnabled() ? "checked" : "";

                $data[] = [
                    "label" => "Notifications push",
                    "value" => "<input name='pushNotifications' type='checkbox' class='data form-control mt-1 smaller' $notificationsEnabled>",
                ];
            }

            if ($categoryLabel === CategoryType::DEMANDE_LIVRAISON) {
                $requesterMailsEnabled = $type && $type->getSendMailRequester() ? "checked" : "";
                $receiverMailsEnabled = $type && $type->getSendMailReceiver() ? "checked" : "";

                $data[] = [
                    "label" => "Envoi d'un email au demandeur",
                    "value" => "<input name='mailRequester' type='checkbox' class='data form-control mt-1 smaller' $requesterMailsEnabled>",
                ];
                $data[] = [
                    "label" => "Envoi d'un email au destinataire",
                    "value" => "<input name='mailReceiver' type='checkbox' class='data form-control mt-1 smaller' $receiverMailsEnabled>",
                ];
            } else {
                if ($categoryLabel === CategoryType::DEMANDE_DISPATCH) {
                    $locationRepository = $this->manager->getRepository(Emplacement::class);

                    $pickLocationOption = $type && $type->getPickLocation() ? "<option value='{$type->getPickLocation()->getId()}'>{$type->getPickLocation()->getLabel()}</option>" : "";

                    $suggestedPickLocationOptions = $type && !empty($type->getSuggestedPickLocations())
                        ? Stream::from($locationRepository->findBy(['id' => $type->getSuggestedPickLocations()]) ?? [])
                        ->map(fn(Emplacement $location) => [
                            "value" => $location->getId(),
                            "label" => $location->getLabel(),
                            "selected" => true,
                        ])
                        ->toArray()
                    : [];

                    $data = array_merge($data, [
                        [
                            "label" => "Emplacement de prise par défaut",
                            "value" => "<select name='pickLocation' data-s2='location' data-parent='body' class='data form-control'>$pickLocationOption</select>",
                        ],
                        [
                            "label" => "Emplacement(s) de prise suggéré(s)",
                            "value" => $formService->macro("select", "suggestedPickLocations", null, false, [
                                "type" => "location",
                                "multiple" => true,
                                "items" => $suggestedPickLocationOptions,
                            ]),
                        ],
                    ]);
                }
            }

            if(in_array($categoryLabel, [CategoryType::PRODUCTION, CategoryType::DEMANDE_DISPATCH])) {
                $locationRepository = $this->manager->getRepository(Emplacement::class);

                $dropLocationOption = $type && $type->getDropLocation() ? "<option value='{$type->getDropLocation()->getId()}'>{$type->getDropLocation()->getLabel()}</option>" : "";
                $suggestedDropLocationOptions = $type && !empty($type->getSuggestedDropLocations())
                    ? Stream::from($locationRepository->findBy(['id' => $type->getSuggestedDropLocations()]) ?? [])
                        ->map(fn(Emplacement $location) => [
                            "value" => $location->getId(),
                            "label" => $location->getLabel(),
                            "selected" => true,
                        ])
                        ->toArray()
                    : [];

                $data = array_merge($data, [
                    [
                        "label" => "Emplacement de dépose par défaut",
                        "value" => "<select name='dropLocation' data-s2='location' data-parent='body' class='data form-control'>$dropLocationOption</select>",
                    ],
                    [
                        "label" => "Emplacement(s) de dépose suggéré(s)",
                        "value" => $formService->macro("select", "suggestedDropLocations", null, false, [
                            "type" => "location",
                            "multiple" => true,
                            "items" => $suggestedDropLocationOptions,
                        ]),
                    ]
                ]);
            }

            if (in_array($categoryLabel, [CategoryType::DEMANDE_HANDLING, CategoryType::DEMANDE_DISPATCH])) {
                $pushNotifications = $this->renderView("form_element.html.twig", [
                    "element" => "radio",
                    "arguments" => [
                        "pushNotifications",
                        "Notifications push",
                        false,
                        [
                            [
                                "label" => "Désactiver",
                                "value" => 0,
                                "checked" => !$type || !$type->isNotificationsEnabled(),
                            ],
                            [
                                "label" => "Activer",
                                "value" => 1,
                                "checked" => $type && $type->isNotificationsEnabled() && !$type->getNotificationsEmergencies(),
                            ],
                            [
                                "label" => "Activer seulement si urgence",
                                "value" => 2,
                                "checked" => $type && $type->isNotificationsEnabled() && $type->getNotificationsEmergencies(),
                            ],
                        ],
                    ],
                ]);

                $entity = [
                    CategoryType::DEMANDE_HANDLING => FixedFieldStandard::ENTITY_CODE_HANDLING,
                    CategoryType::DEMANDE_DISPATCH => FixedFieldStandard::ENTITY_CODE_DISPATCH,
                ];

                $emergencies = $fixedFieldRepository->getElements($entity[$categoryLabel], FixedFieldStandard::FIELD_CODE_EMERGENCY);

                $data = array_merge($data, [
                    [
                        'breakline' => true,
                    ],
                    [
                        "label" => "Notifications push",
                        "value" => $pushNotifications,
                    ],
                    [
                        "label" => "Pour les valeurs",
                        "value" => $formService->macro("select", "notificationEmergencies", null, $type && $type->getNotificationsEmergencies(), [
                            "type" => "",
                            "noEmptyOption" => true,
                            "multiple" => true,
                            "attributes" => [
                                "data-parent" => "body"
                            ],
                            "items" => Stream::from($emergencies)
                                ->map(fn(string $emergency) => [
                                    "label" => $emergency,
                                    "value" => $emergency,
                                    "selected" => $type && in_array($emergency, $type->getNotificationsEmergencies() ?? []),
                                ])
                                ->toArray()
                        ]),
                        "hidden" => !$type || !$type->isNotificationsEnabled() || !$type->getNotificationsEmergencies(),
                    ],
                ]);
            }

            if(in_array($categoryLabel, [CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT, CategoryType::ARRIVAGE])) {
                $requiredMark = !in_array($categoryLabel, [CategoryType::ARRIVAGE]) ? "*" : "";
                $data[] = [
                    "label" => "Logo$requiredMark",
                    "value" => $this->renderView("form_element.html.twig", [
                        "element" => "image",
                        "arguments" => [
                            "logo",
                            null,
                            !!$requiredMark,
                            $type?->getLogo()?->getFullPath(),
                        ],
                    ]),
                ];
            }

            if(in_array($categoryLabel, [CategoryType::DEMANDE_DISPATCH, CategoryType::ARRIVAGE])) {
                $data[] = [
                    "label" => "Par défaut",
                    "value" => $formService->macro("switch", "isDefault", null, true, [
                        ["label" => "Oui", "value" => 1, "checked" => (bool) ($type?->isDefault())],
                        ["label" => "Non", "value" => 0, "checked" => $type ? !$type->isDefault() : null],
                    ]),
                ];
            }
        } else {
            $data = [
                [
                    "label" => "Description",
                    "value" => $type?->getDescription(),
                ],
            ];

            if (in_array($categoryLabel, [CategoryType::ARTICLE, CategoryType::DEMANDE_DISPATCH, CategoryType::PRODUCTION])) {
                $data[] = [
                    "label" => "Couleur",
                    "value" => $type ? "<div class='dt-type-color' style='background: {$type->getColor()}'></div>" : null,
                ];
            }

            if (in_array($categoryLabel, [CategoryType::DEMANDE_LIVRAISON, CategoryType::DEMANDE_COLLECTE])) {
                $data[] = [
                    "label" => "Notifications push",
                    "value" => $type?->isNotificationsEnabled() ? "Activées" : "Désactivées",
                ];
            }

            if ($categoryLabel === CategoryType::DEMANDE_LIVRAISON) {
                $data[] = [
                    "label" => "Envoi d'un email au demandeur",
                    "value" => $type?->getSendMailRequester() ? "Activées" : "Désactivées",
                ];
                $data[] = [
                    "label" => "Envoi d'un email au destinataire",
                    "value" => $type?->getSendMailReceiver() ? "Activées" : "Désactivées",
                ];
            }

            if ($categoryLabel === CategoryType::DEMANDE_DISPATCH) {
                $locationRepository = $this->manager->getRepository(Emplacement::class);

                $suggestedPickLocations = Stream::from($locationRepository->findBy(['id' => $type->getSuggestedPickLocations()]) ?? [])
                    ->map(fn(Emplacement $location) => $location->getLabel())
                    ->join(', ');

                $data = array_merge($data, [
                    [
                        "label" => "Emplacement de prise par défaut",
                        "value" => $this->formatService->location($type?->getPickLocation()),
                    ],
                    [
                        "label" => "Emplacement(s) de prise suggéré(s)",
                        "value" => $suggestedPickLocations,
                    ],
                ]);
            }

            if(in_array($categoryLabel, [CategoryType::PRODUCTION, CategoryType::DEMANDE_DISPATCH])) {
                $locationRepository = $this->manager->getRepository(Emplacement::class);

                $suggestedDropLocations = Stream::from($locationRepository->findBy(['id' => $type->getSuggestedDropLocations()]) ?? [])
                    ->map(fn(Emplacement $location) => $location->getLabel())
                    ->join(', ');

                $data = array_merge($data, [
                    [
                        "label" => "Emplacement de dépose par défaut",
                        "value" => $this->formatService->location($type?->getDropLocation()),
                    ],
                    [
                        "label" => "Emplacement(s) de dépose suggéré(s)",
                        "value" => $suggestedDropLocations,
                    ],
                ]);
            }

            if (in_array($categoryLabel, [CategoryType::DEMANDE_HANDLING, CategoryType::DEMANDE_DISPATCH])) {
                $hasNotificationsEmergencies = $type?->isNotificationsEnabled() && $type?->getNotificationsEmergencies();
                if ($hasNotificationsEmergencies) {
                    $data[] = [
                        "breakline" => true,
                    ];
                }

                $data[] = [
                    "label" => "Notifications push",
                    "value" => !$type?->isNotificationsEnabled()
                        ? "Désactivées"
                        : ($type?->getNotificationsEmergencies()
                            ? "Activées seulement si urgence"
                            : "Activées"),
                ];

                if ($hasNotificationsEmergencies) {
                    $data[] = [
                        "label" => "Pour les valeurs",
                        "value" => join(", ", $type?->getNotificationsEmergencies() ?: []),
                    ];
                }
            }

            if(in_array($categoryLabel, [CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT, CategoryType::ARRIVAGE])) {
                $data[] = [
                    "label" => "Logo",
                    "value" => $type?->getLogo()
                        ? "<img src='{$type?->getLogo()?->getFullPath()}' alt='Logo du type' style='max-height: 30px; max-width: 30px;'>"
                        : "",
                ];
            }

            if(in_array($categoryLabel, [CategoryType::DEMANDE_DISPATCH, CategoryType::ARRIVAGE])) {
                $data[] = [
                    "label" => "Par défaut",
                    "value" => $this->formatService->bool($type->isDefault()) ?: "Non",
                ];
            }
        }

        return $this->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    /**
     * @Route("/champs-libres/api/{type}", name="settings_free_field_api", options={"expose"=true})
     */
    public function freeFieldApi(Request $request, EntityManagerInterface $manager, UserService $userService, ?Type $type = null): Response {
        $edit = $request->query->getBoolean("edit");
        $hasEditRight = $userService->hasRightFunction(Menu::PARAM, Action::EDIT);

        $class = "form-control data";

        $categorieCLRepository = $manager->getRepository(CategorieCL::class);

        $categoryLabels = $categorieCLRepository->findByLabel([
            CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE,
        ]);
        $rows = [];
        $freeFields = $type ? $type->getChampsLibres() : [];
        foreach ($freeFields as $freeField) {
            if ($freeField->getTypage() === FreeField::TYPE_BOOL) {
                $typageCLFr = "Oui/Non";
            } else if ($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                $typageCLFr = "Nombre";
            } else if ($freeField->getTypage() === FreeField::TYPE_TEXT) {
                $typageCLFr = "Texte";
            } else if ($freeField->getTypage() === FreeField::TYPE_LIST) {
                $typageCLFr = "Liste";
            } else if ($freeField->getTypage() === FreeField::TYPE_DATE) {
                $typageCLFr = "Date";
            } else if ($freeField->getTypage() === FreeField::TYPE_DATETIME) {
                $typageCLFr = "Date et heure";
            } else if ($freeField->getTypage() === FreeField::TYPE_LIST_MULTIPLE) {
                $typageCLFr = "Liste multiple";
            } else {
                $typageCLFr = "";
            }

            $defaultValue = null;
            if ($freeField->getTypage() == FreeField::TYPE_BOOL) {
                if (!$edit) {
                    $defaultValue = ($freeField->getDefaultValue() === null || $freeField->getDefaultValue() === "")
                        ? ""
                        : ($freeField->getDefaultValue() ? "Oui" : "Non");
                } else {
                    if ($freeField->getDefaultValue() === "") {
                        $freeField->setDefaultValue(null);
                    }

                    $defaultValue = $this->renderView("form_element.html.twig", [
                        "element" => "switch",
                        "arguments" => [
                            "defaultValue",
                            null,
                            false,
                            [
                                ["label" => "Oui", "value" => 1, "checked" => $freeField->getDefaultValue()],
                                [
                                    "label" => "Non", "value" => 0,
                                    "checked" => $freeField->getDefaultValue() !== null && !$freeField->getDefaultValue(),
                                ],
                                [
                                    "label" => "Aucune", "value" => null,
                                    "checked" => $freeField->getDefaultValue() === null,
                                ],
                            ],
                        ],
                    ]);

                    $defaultValue = "<div class='wii-switch-small'>$defaultValue</div>";
                }
            } else if($freeField->getTypage() === FreeField::TYPE_DATETIME || $freeField->getTypage() === FreeField::TYPE_DATE) {
                $defaultValueDate = $freeField->getDefaultValue()
                    ? new DateTime(str_replace("/", "-", $freeField->getDefaultValue()))
                    : null;
                if(!$edit) {
                    $defaultValue = $defaultValueDate ? $defaultValueDate->format('d/m/Y H:i') : "";
                } else {
                    if($freeField->getTypage() === FreeField::TYPE_DATETIME) {
                        $defaultValueDate = $defaultValueDate ? $defaultValueDate->format("Y-m-d\\TH:i") : "";
                        $defaultValue = "<input type='datetime-local' name='defaultValue' class='$class' value='$defaultValueDate'/>";
                    } else {
                        $defaultValueDate = $defaultValueDate ? $defaultValueDate->format("Y-m-d") : "";
                        $defaultValue = "<input type='date' name='defaultValue' class='$class' value='$defaultValueDate'/>";
                    }
                }
            } else if($edit && $freeField->getTypage() === FreeField::TYPE_LIST) {
                $options = Stream::from($freeField->getElements())
                    ->map(fn(string $value) => "<option value='$value' " . ($value === $freeField->getDefaultValue() ? "selected" : "") . ">$value</option>")
                    ->join("");

                $defaultValue = "<select name='defaultValue' class='form-control data' data-global-error='Valeur par défaut'><option></option>$options</select>";
            } else if($freeField->getTypage() !== FreeField::TYPE_LIST_MULTIPLE) {
                if(!$edit) {
                    $defaultValue = $freeField->getDefaultValue();
                } else {
                    $inputType = $freeField->getTypage() === FreeField::TYPE_NUMBER ? "number" : "text";
                    $defaultValue = "<input type='$inputType' name='defaultValue' class='$class' value='{$freeField->getDefaultValue()}'/>";
                }
            }

            if ($edit) {
                $displayedCreate = $freeField->getDisplayedCreate() ? "checked" : "";
                $requiredCreate = $freeField->isRequiredCreate() ? "checked" : "";
                $requiredEdit = $freeField->isRequiredEdit() ? "checked" : "";
                $elements = join(";", $freeField->getElements());

                $categories = Stream::from($categoryLabels)
                    ->map(function(CategorieCL $category) use ($freeField) {
                        $selected = $freeField->getCategorieCL()->getLabel() === $category->getLabel() ? 'selected' : '';
                        return "<option value='{$category->getId()}' $selected>{$category->getLabel()}</option>";
                    })
                    ->join("");

                $rows[] = [
                    "id" => $freeField->getId(),
                    "actions" => "<input type='hidden' class='$class' name='id' value='{$freeField->getId()}'>
                        <button class='btn btn-silent delete-row' data-id='{$freeField->getId()}'><i class='wii-icon wii-icon-trash text-primary'></i></button>",
                    "label" => "<input type='text' name='label' class='$class' value='{$freeField->getLabel()}' required/>",
                    "appliesTo" => "<select name='category' class='$class' required>$categories</select>",
                    "type" => $typageCLFr,
                    "displayedCreate" => "<input type='checkbox' name='displayedCreate' class='$class' $displayedCreate/>",
                    "requiredCreate" => "<input type='checkbox' name='requiredCreate' class='$class' $requiredCreate/>",
                    "requiredEdit" => "<input type='checkbox' name='requiredEdit' class='$class' $requiredEdit/>",
                    "defaultValue" => "<form>$defaultValue</form>",
                    "elements" => in_array($freeField->getTypage(), [FreeField::TYPE_LIST, FreeField::TYPE_LIST_MULTIPLE])
                        ? "<input type='text' name='elements' required class='$class' value='$elements'/>"
                        : "",
                    "minCharactersLength" => $freeField->getTypage() === FreeField::TYPE_TEXT
                        ? "<input type='number' name='minCharactersLength' min='1' class='$class' value='{$freeField->getMinCharactersLength()}'/>"
                        : "",
                    "maxCharactersLength" => $freeField->getTypage() === FreeField::TYPE_TEXT
                        ? "<input type='number' name='maxCharactersLength' min='1' class='$class' value='{$freeField->getMaxCharactersLength()}'/>"
                        : "",
                ];
            } else {
                $rows[] = [
                    "id" => $freeField->getId(),
                    "actions" => "<button class='btn btn-silent delete-row' data-id='{$freeField->getId()}'><i class='wii-icon wii-icon-trash text-primary'></i></button>",
                    "label" => $freeField->getLabel() ?: 'Non défini',
                    "appliesTo" => $freeField->getCategorieCL() ? ucfirst($freeField->getCategorieCL()
                        ->getLabel()) : "",
                    "type" => $typageCLFr,
                    "displayedCreate" => ($freeField->getDisplayedCreate() ? "oui" : "non"),
                    "requiredCreate" => ($freeField->isRequiredCreate() ? "oui" : "non"),
                    "requiredEdit" => ($freeField->isRequiredEdit() ? "oui" : "non"),
                    "defaultValue" => $defaultValue ?? "",
                    "elements" => $freeField->getTypage() == FreeField::TYPE_LIST || $freeField->getTypage() == FreeField::TYPE_LIST_MULTIPLE ? $this->renderView('free_field/freeFieldElems.html.twig', ['elems' => $freeField->getElements()]) : '',
                    "minCharactersLength" => $freeField->getMinCharactersLength() ?? "",
                    "maxCharactersLength" => $freeField->getMaxCharactersLength() ?? "",
                ];
            }
        }

        $typeFreePages = $type
            && in_array($type->getCategory()->getLabel(), [
                CategoryType::MOUVEMENT_TRACA, CategoryType::SENSOR, CategoryType::RECEPTION,
            ]);
        if ($hasEditRight && ($edit || $typeFreePages)) {
            $rows[] = [
                "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
                "label" => "",
                "appliesTo" => "",
                "type" => "",
                "displayedCreate" => "",
                "requiredCreate" => "",
                "requiredEdit" => "",
                "defaultValue" => "",
                "elements" => "",
                "minCharactersLength" => "",
                "maxCharactersLength" => "",
            ];
        }

        return $this->json([
            "data" => $rows,
        ]);
    }

    /**
     * @Route("/champ-libre/supprimer/{entity}", name="settings_free_field_delete", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteFreeField(EntityManagerInterface $manager, FreeField $entity): Response {
        if(!$entity->getFilters()->isEmpty()) {
            $filter = $manager->getRepository(FiltreRef::class)->findOneBy(['champLibre' => $entity]);
            $manager->remove($filter);
        }

        if (($entity->getTypage() === FreeField::TYPE_LIST || $entity->getTypage() === FreeField::TYPE_LIST_MULTIPLE) && $entity->getElementsTranslations()){
            foreach($entity->getElementsTranslations() as $elementTranslationSource){
                foreach ($elementTranslationSource->getTranslations() as $elementTranslation){
                    $manager->remove($elementTranslation);
                }
                $manager->remove($elementTranslationSource);
            }
        }

        if($entity->getDefaultValueTranslation()){
            foreach($entity->getDefaultValueTranslation()->getTranslations() as $defaultValueTranslation){
                $manager->remove($defaultValueTranslation);
            }
            $manager->remove($entity->getDefaultValueTranslation());
        }

        if($entity->getLabelTranslation()){
            foreach($entity->getLabelTranslation()->getTranslations() as $freeFieldTranslation){
                $manager->remove($freeFieldTranslation);
            }
            $manager->remove($entity->getLabelTranslation());
        }

        $manager->remove($entity);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le champ libre a été supprimé",
        ]);
    }

    /**
     * @Route("/champ-fixe/sous-lignes/{entity}", name="settings_sublines_fixed_field_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function subLinesFixedFieldApi(EntityManagerInterface $entityManager, string $entity, FormService $formService): Response
    {
        $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFixedField::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $rows = Stream::from($subLineFieldsParamRepository->findByEntityCode($entity))
            ->map(function (SubLineFixedField $field) use ($formService, $typeRepository) {

                $label = ucfirst($field->getFieldLabel());

                $fieldEntityCode = $field->getEntityCode();
                $isDisplayUnderCondition = $field->isDisplayedUnderCondition();
                $isDisplayUnderConditionDisabled = in_array($field->getFieldCode(), SubLineFixedField::DISABLED_DISPLAYED_UNDER_CONDITION[$fieldEntityCode] ?? []);
                $isRequiredDisabled = in_array($field->getFieldCode(), SubLineFixedField::DISABLED_REQUIRED[$fieldEntityCode] ?? []);

                $isDisplayUnderConditionDisplayed = !$isDisplayUnderConditionDisabled && $isDisplayUnderCondition;

                $rawConditionFixedFieldValue = $field->getConditionFixedFieldValue() ?? [];
                $conditionFixedFieldValue = !empty($rawConditionFixedFieldValue)
                    ? $typeRepository->findBy(['id' => $rawConditionFixedFieldValue])
                    : [];

                $conditionFixedFieldOptionsSelected = Stream::from(!$isDisplayUnderConditionDisabled ? $conditionFixedFieldValue : [])
                    ->map(fn(Type $type) => [
                        'value' => $type->getId(),
                        'label' => $type->getLabel(),
                        'selected' => true,
                    ])
                    ->toArray();

                $displayConditions = Stream::from(SubLineFixedField::DISPLAY_CONDITIONS[$field->getEntityCode()] ?? [])
                    ->map(fn(string $condition) => [
                        'value' => $condition,
                        'label' => $condition,
                        'selected' => $condition === $field->getConditionFixedField(),
                    ])
                    ->toArray();

                $labelAttributes = "class='font-weight-bold'";
                if (in_array($field->getFieldCode(), SubLineFixedField::FREE_ELEMENTS_FIELDS[$field->getEntityCode()] ?? [])) {
                    $modal = strtolower($field->getFieldCode());
                    $labelAttributes = "class='font-weight-bold btn-link pointer' data-target='#modal-fixed-field-$modal' data-toggle='modal'";
                }

                return [
                    "label" => "<span $labelAttributes>$label</span>" . $formService->macro("hidden", "id", $field->getId(), []),
                    "displayed" => $formService->macro("checkbox", "displayed", null, false, $field->isDisplayed(), [
                        "additionalAttributes" => [
                            ['name' => "onchange", "value" => "changeDisplayRefArticleTable($(this))"],
                        ],
                    ]),
                    "displayedUnderCondition" => $formService->macro("checkbox", "displayedUnderCondition", null, false, $isDisplayUnderCondition, [
                        "disabled" => $isDisplayUnderConditionDisabled,
                        "additionalAttributes" => [
                            ['name' => "onchange", "value" => "changeDisplayRefArticleTable($(this))"],
                        ],
                    ]),
                    "conditionFixedField" => $isDisplayUnderConditionDisabled
                        ? ''
                        : $formService->macro("select", "conditionFixedField", null, false, [
                            "items" => $displayConditions,
                            "additionalAttributes" => $isDisplayUnderConditionDisplayed
                                ? []
                                : [['name' => "hidden", "value" => 'hidden']],
                        ]),
                    "conditionFixedFieldValue" => $isDisplayUnderConditionDisabled
                        ? ''
                        : $formService->macro("select", "conditionFixedFieldValue", null, false, [
                        "type" => "referenceType",
                        "items" => $conditionFixedFieldOptionsSelected,
                        "hidden" => !$isDisplayUnderConditionDisplayed,
                        "multiple" => true,
                    ]),
                    "required" => $formService->macro("checkbox", "required", null, false, $field->isRequired(), [
                        "disabled" => $isRequiredDisabled,
                    ]),
                ];
            })
            ->toArray();

        return $this->json([
            "data" => $rows,
        ]);
    }

    #[Route("/champ-fixe/{entity}", name: "settings_fixed_field_api", options: ['expose' => true], methods: ["GET"], condition: "request.isXmlHttpRequest()")]
    public function fixedFieldApi(Request                 $request,
                                        EntityManagerInterface  $entityManager,
                                        FormService             $formService,
                                        string                  $entity): JsonResponse {
        $type = $request->query->has("type") ? $entityManager->getRepository(Type::class)->find($request->query->get("type")) : null;

        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $entityNeeded = $type ? FixedFieldByType::class : FixedFieldStandard::class;
        $fixedFieldRepository = $entityManager->getRepository($entityNeeded);

        $fields = $fixedFieldRepository->findBy(["entityCode" => $entity]);
        $rows = Stream::from($fields)
            ->map(function (FixedField $field) use ($entityNeeded, $type, $edit, $formService, $entity): array {
                $isParams = $entityNeeded === FixedFieldByType::class ? ["type" => $type] : [];

                $label = ucfirst($field->getFieldLabel());
                $code = $field->getFieldCode();


                $displayedCreate = $field->isDisplayedCreate(...$isParams);
                $requiredCreate = $field->isRequiredCreate(...$isParams);
                $displayedEdit = $field->isDisplayedEdit(...$isParams);
                $requiredEdit = $field->isRequiredEdit(...$isParams);

                $keptInMemoryDisabled = in_array($code, FixedField::MEMORY_UNKEEPABLE_FIELDS[$entity] ?? []);
                $keptInMemory = !$keptInMemoryDisabled && $field->isKeptInMemory(...$isParams);

                if ($entityNeeded === FixedFieldStandard::class) {
                    $filtersDisabled = !in_array($code, FixedField::FILTERED_FIELDS[$entity] ?? []);
                    $displayedFilters = !$filtersDisabled && $field->isDisplayedFilters();
                }

                $filterOnly = in_array($code, FixedField::FILTER_ONLY_FIELDS);
                $requireDisabled = $filterOnly || in_array($code, FixedField::ALWAYS_REQUIRED_FIELDS[$entity] ?? []);
                $displayDisabled = $filterOnly || in_array($field->getFieldCode(), FixedField::ALWAYS_DISPLAYED_FIELDS[$entity] ?? []);


                if ($edit) {
                    $labelAttributes = "";
                    if ($field->getElements() !== null) {
                        $modal = strtolower($field->getFieldCode());
                        $labelAttributes = "btn-link pointer' data-target='#modal-fixed-field-$modal' data-toggle='modal'";
                    }

                    $row = [
                        "label" => "<span class='font-weight-bold $labelAttributes'>$label</span>" . $formService->macro("hidden", "id", $field->getId(), []),
                        "displayedCreate" => $formService->macro("checkbox", "displayedCreate", null, false, $displayedCreate, [
                            "disabled" => $filterOnly || $displayDisabled,
                        ]),
                        "displayedEdit" => $formService->macro("checkbox", "displayedEdit", null, false, $displayedEdit, [
                            "disabled" => $filterOnly || $displayDisabled,
                        ]),
                        "requiredCreate" => $formService->macro("checkbox", "requiredCreate", null, false, $requiredCreate, [
                            "disabled" => $requireDisabled,
                        ]),
                        "requiredEdit" => $formService->macro("checkbox", "requiredEdit", null, false, $requiredEdit, [
                            "disabled" => $requireDisabled,
                        ]),

                    ];

                    if ($entityNeeded === FixedFieldStandard::class) {
                        $row["displayedFilters"] = $formService->macro("checkbox", "displayedFilters", null, false, $displayedFilters, [
                            "disabled" => $filtersDisabled,
                        ]);
                    }

                    if ($entity === FixedFieldStandard::ENTITY_CODE_ARRIVAGE) {
                        $row["keptInMemory"] = $formService->macro("checkbox", "keptInMemory", null, false, $keptInMemory, [
                            "disabled" => $keptInMemoryDisabled,
                        ]);
                    }
                } else {
                    $row = [
                        "label" => "<span class='font-weight-bold'>$label</span>",
                        "displayedCreate" => $this->formatService->bool($field->isDisplayedCreate(...$isParams)),
                        "displayedEdit" => $this->formatService->bool($field->isDisplayedEdit(...$isParams)),
                        "requiredCreate" => $this->formatService->bool($field->isRequiredCreate(...$isParams)),
                        "requiredEdit" => $this->formatService->bool($field->isRequiredEdit(...$isParams)),
                        "displayedFilters" => $this->formatService->bool(in_array($field->getFieldCode(), FixedField::FILTERED_FIELDS[$entity] ?? []) && $field->isDisplayedFilters(...$isParams)),
                    ];

                    if ($entity === FixedFieldStandard::ENTITY_CODE_ARRIVAGE) {
                        $row["keptInMemory"] = $this->formatService->bool($field->isKeptInMemory(...$isParams));
                    }
                }
                return $row;
            });

        return $this->json([
            "data" => $rows->toArray(),
        ]);
    }

    private function canDelete(): bool {
        return $this->userService->hasRightFunction(Menu::PARAM, Action::DELETE);
    }

    /**
     * @Route("/frequences-api", name="settings_frequencies_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function frequenciesApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $data = [];
        $inventoryFrequencyRepository = $manager->getRepository(InventoryFrequency::class);

        foreach ($inventoryFrequencyRepository->findAll() as $frequence) {
            if ($edit) {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row w-50' data-id='{$frequence->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        <input type='hidden' name='frequenceId' class='data' value='{$frequence->getId()}'/>",
                    "label" => "<input type='text' name='label' class='form-control data needed' value='{$frequence->getLabel()}' data-global-error='Libellé'/>",
                    "nb_months" => "<input type='number' name='nbMonths' min='1' class='form-control data needed' value='{$frequence->getNbMonths()}' data-global-error='Nombre de mois'/>",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$frequence->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    ",
                    "label" => $frequence->getLabel(),
                    "nb_months" => $frequence->getNbMonths(),
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "label" => "",
            "nb_months" => "",
        ];

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/frequences/supprimer/{entity}", name="settings_delete_frequency", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function deleteFrequency(EntityManagerInterface $entityManager, InventoryFrequency $entity): Response {
        if ($entity->getCategories()->isEmpty()) {
            $entityManager->remove($entity);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La ligne a bien été supprimée",
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Cette fréquence est liée à des catégories. Vous ne pouvez pas la supprimer.",
            ]);
        }
    }

    /**
     * @Route("/categories-api", name="settings_categories_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function categoriesApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $data = [];
        $inventoryCategoryRepository = $manager->getRepository(InventoryCategory::class);
        $inventoryFrequencyRepository = $manager->getRepository(InventoryFrequency::class);

        $frequencies = $inventoryFrequencyRepository->findAll();
        $frequencyOptions = Stream::from($frequencies)
            ->map(fn(InventoryFrequency $n) => [
                "id" => $n->getId(),
                "label" => $n->getLabel(),
            ])
            ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"]);

        foreach ($inventoryCategoryRepository->findAll() as $category) {
            if ($edit) {
                $selectedFrequency = $category->getFrequency()?->getLabel();
                $emptySelected = empty($selectedFrequency) ? 'selected' : '';
                $frequencySelectContent = Stream::from($frequencyOptions)
                    ->map(function(array $n) use ($selectedFrequency) {
                        $selected = $n['label'] === $selectedFrequency ? "selected" : '';
                        return "<option value='{$n["id"]}' {$selected}>{$n["label"]}</option>";
                    })
                    ->prepend("<option disabled {$emptySelected}>Sélectionnez une fréquence</option>")
                    ->join("");

                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row w-50' data-id='{$category->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        <input type='hidden' name='categoryId' class='data' value='{$category->getId()}'/>
                    ",
                    "label" => "<input type='text' name='label' class='{$class} needed' value='{$category->getLabel()}' data-global-error='Libellé'/>",
                    "frequency" => "<select name='frequency' class='{$class} needed' data-global-error='Fréquences'>
                        {$frequencySelectContent}
                    </select>",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$category->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        ",
                    "label" => $category->getLabel(),
                    "frequency" => $category->getFrequency()?->getLabel(),
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "label" => "",
            "frequency" => "",
        ];

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/categories/supprimer/{entity}", name="settings_delete_category", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function deleteCategory(EntityManagerInterface $entityManager,
                                   InventoryCategory $entity): Response {

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $linkedReference = $referenceArticleRepository->countByCategory($entity);


        if ($linkedReference > 0) {
            return $this->json([
                "success" => false,
                "msg" => "La catégorie est liée à des références articles",
            ]);
        }

        if ($entity->getFrequency()) {
            $entity->getFrequency()->removeCategory($entity);
            $entity->setFrequency(null);
        }

        $entityManager->remove($entity);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La ligne a bien été supprimée",
        ]);
    }

    /**
     * @Route("/mission-rules-force", name="settings_mission_rules_force", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function missionRulesForce(EntityManagerInterface $manager, InvMissionService $invMissionService): Response {
        $rules = $manager->getRepository(InventoryMissionRule::class)->findBy(['active' => true]);

        foreach($rules as $rule) {
            $invMissionService->generateMission($rule);
        }

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/mission-rules-api", name="settings_mission_rules_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function missionRulesApi(EntityManagerInterface $manager): Response {
        $data = [];
        $missionRuleRepository = $manager->getRepository(InventoryMissionRule::class);

        /** @var InventoryMissionRule $mission */
        foreach ($missionRuleRepository->findAll() as $mission) {
            $data[] = [
                "actions" => $this->renderView("utils/action-buttons/dropdown.html.twig", [
                    "actions" => [
                        [
                            "title" => "Modifier",
                            "actionOnClick" => true,
                            "attributes" => [
                                "data-id" => $mission->getId(),
                                "onclick" => "editMissionRule($(this))",
                            ],
                        ],
                        [
                            "title" => "Annuler la planification",
                            "icon" => "bg-black wii-icon wii-icon-cancel-black wii-icon-17px",
                            "attributes" => [
                                "data-id" => $mission->getId(),
                                "class" => "pointer",
                                "onclick" => "cancelInventoryMission($(this))",
                            ],
                        ],
                        [
                            "title" => "Supprimer la planification",
                            "icon" => "bg-black wii-icon wii-icon-trash-black wii-icon-17px",
                            "attributes" => [
                                "data-id" => $mission->getId(),
                                "class" => "pointer",
                                "onclick" => "deleteInventoryMission($(this))",
                            ],
                        ],
                    ],
                ]),
                "missionType" => $mission->getMissionType() ? InventoryMission::TYPES_LABEL[$mission->getMissionType()] ?? '' : '',
                "label" => $mission->getLabel(),
                "categories" => Stream::from($mission->getCategories())
                    ->map(fn(InventoryCategory $category) => $category->getLabel())
                    ->join(", "),
                "periodicity" => ScheduleRule::FREQUENCIES_LABELS[$mission->getFrequency()] ?? null,
                "duration" => $mission->getDuration() . ' ' . (InventoryMissionRule::DURATION_UNITS_LABELS[$mission->getDurationUnit()] ?? null),
                "requester" => $this->getFormatter()->user($mission->getRequester()),
                "creator" => $this->getFormatter()->user($mission->getCreator()),
                "lastExecution" => $mission->getLastRun() ? $mission->getLastRun()->format('d/m/Y H:i:s') : "",
                "active" => $this->getFormatter()->bool($mission->isActive()),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/mission-rules/supprimer/{entity}", name="settings_delete_mission_rule", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function deleteMissionRules(EntityManagerInterface $entityManager, InventoryMissionRule $entity): Response {

        if (!empty($entity->getLocations())) {
            foreach ($entity->getLocations() as $location) {
                $location->removeInventoryMissionRule($entity);
            }
        }

        if (!empty($entity->getCreatedMissions())) {
            foreach ($entity->getCreatedMissions() as $createdMission) {
                $createdMission->setCreator(null);
            }
        }

        $entityManager->remove($entity);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La ligne a bien été supprimée",
        ]);
    }

    /**
     * @Route("/types-litige-api", name="types_litige_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_RECEP}, mode=HasPermission::IN_JSON)
     */
    public function typesLitigeApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $data = [];
        $typeRepository = $manager->getRepository(Type::class);
        $typesLitige = $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]);
        foreach ($typesLitige as $type) {
            if ($edit) {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$type->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        <input type='hidden' name='typeLitigeId' class='data' value='{$type->getId()}'/>",
                    "label" => "<input type='text' name='label' class='form-control data needed' value='{$type->getLabel()}' data-global-error='Libellé'/>",
                    "description" => "<input type='text' name='description' class='form-control data' value='{$type->getDescription()}' data-global-error='Description'/>",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$type->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    ",
                    "label" => $type->getLabel(),
                    "description" => $type->getDescription(),
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "label" => "",
            "description" => "",
        ];

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/types_litige/supprimer/{entity}", name="settings_delete_type_litige", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_RECEP}, mode=HasPermission::IN_JSON)
     */
    public function deleteTypeLitige(EntityManagerInterface $entityManager, Type $entity): Response {
        if ($entity->getDisputes()->isEmpty()) {
            $entityManager->remove($entity);
            $entityManager->flush();
            return $this->json([
                "success" => true,
                "msg" => "Le type de litige a bien été supprimé",
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Ce type de litige est utilisé, vous ne pouvez pas le supprimer.",
            ]);
        }
    }

    /**
     * @Route("/types_litige_api/edit/translate", name="settings_edit_types_litige_translations_api", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT})
     */
    public function apiEditTranslationsTypeLitige(EntityManagerInterface $manager,
                                                  TranslationService     $translationService): JsonResponse {
        $typeRepository = $manager->getRepository(Type::class);
        $typesLitige = $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]);

        foreach ($typesLitige as $type) {
            if ($type->getLabelTranslation() === null) {
                $translationService->setDefaultTranslation($manager, $type, $type->getLabel());
            }
        }
        $manager->flush();

        $html = $this->renderView('settings/modal_edit_translations_content.html.twig', [
            'lines' => $typesLitige,
        ]);

        return new JsonResponse([
            'success' => true,
            'html' => $html,
        ]);
    }

    /**
     * @Route("/types_litige/edit/translate", name="settings_edit_types_litige_translations", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editTranslations(Request                $request,
                                     EntityManagerInterface $manager,
                                     TranslationService     $translationService): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            $typeRepository = $manager->getRepository(Type::class);
            $typesLitige = json_decode($data['lines'], true);

            foreach ($typesLitige as $typeId) {
                $type = $typeRepository->find($typeId);

                $name = 'labels-'.$typeId;
                $labels = $data[$name];
                $labelTranslationSource = $type->getLabelTranslation();

                $translationService->editEntityTranslations($manager, $labelTranslationSource, $labels);
            }

            $manager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => "Les traductions ont bien été modifiées.",
            ]);
        }
        throw new BadRequestHttpException();
    }

    public function getRequestTemplates(TypeRepository $typeRepository, RequestTemplateRepository $requestTemplateRepository, string $templateType) {
        $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::REQUEST_TEMPLATE, $templateType);

        $templates = Stream::from($requestTemplateRepository->findBy(["type" => $type]))
            ->map(fn(RequestTemplate $template) => [
                "label" => $template->getName(),
                "value" => $template->getId(),
            ])
            ->toArray();

        return [
            "type" => $templateType,
            "templates" => $templates,
        ];
    }

    public function getAlertTemplates(AlertTemplateRepository $alertTemplateRepository) {
        $templates = Stream::from($alertTemplateRepository->findAll())
            ->map(fn(AlertTemplate $template) => [
                "label" => $template->getName(),
                "value" => $template->getId(),
            ])
            ->toArray();

        return [
            "templates" => $templates,
        ];
    }

    /**
     * @Route("/groupes_visibilite-api", name="settings_visibility_group_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_VISIBILITY_GROUPS})
     */
    public function visibiliteGroupApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $data = [];
        $visibilityGroupRepository = $manager->getRepository(VisibilityGroup::class);

        foreach ($visibilityGroupRepository->findAll() as $visibilityGroup) {
            if ($edit) {
                $isActive = $visibilityGroup->isActive() == 1 ? 'checked' : "";
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row' data-id='{$visibilityGroup->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button><input type='hidden' name='visibilityGroupId' class='data' value='{$visibilityGroup->getId()}'/>" : "",
                    "label" => "<input type='text' name='label' class='form-control data needed' value='{$visibilityGroup->getLabel()}' data-global-error='Libellé'/>",
                    "description" => "<input type='text' name='description' class='form-control data needed' value='{$visibilityGroup->getDescription()}' data-global-error='Description'/>",
                    "actif" => "<div class='checkbox-container'><input type='checkbox' name='actif' class='form-control data' {$isActive}/></div>",
                ];
            } else {
                $data[] = [
                    "actions" => $this->canDelete() ? "
                        <button class='btn btn-silent delete-row' data-id='{$visibilityGroup->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button><input type='hidden' name='visibilityGroupId' class='data' value='{$visibilityGroup->getId()}'/>" : "",
                    "label" => $visibilityGroup->getLabel(),
                    "description" => $visibilityGroup->getDescription(),
                    "actif" => $visibilityGroup->isActive() ? "Oui" : "Non",
                ];
            }
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/groupes_visibilite/supprimer/{entity}", name="settings_visibility_group_delete", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteVisibilityGroup(EntityManagerInterface $manager, VisibilityGroup $entity) {
        $visibilityGroup = $manager->getRepository(VisibilityGroup::class)->find($entity);
        if ($visibilityGroup->getArticleReferences()->isEmpty()) {
            $manager->remove($entity);
            $manager->flush();
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Impossible de supprimer le groupe de visibilité car il est associé à des articles/références",
            ]);
        }
        return $this->json([
            "success" => true,
            "msg" => "Le groupe de visibilité a été supprimé",
        ]);
    }

    /**
     * @Route("/tag-template-api", name="settings_tag_template_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_BILL}, mode=HasPermission::IN_JSON)
     */
    public function tagTemplateApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $data = [];
        $tagTemplateRepository = $manager->getRepository(TagTemplate::class);
        $natureRepository = $manager->getRepository(Nature::class);
        $typeRepository = $manager->getRepository(Type::class);

        $categoryTypeArrivage = $manager->getRepository(CategoryType::class)->findBy(['label' => CategoryType::ARTICLE]);

        $natures = $natureRepository->findAll();
        $types = $typeRepository->findBy(['category' => $categoryTypeArrivage]);

        foreach ($tagTemplateRepository->findAll() as $tagTemplate) {
            if ($edit) {
                $isArrival = $tagTemplate->getModule() === CategoryType::ARRIVAGE ? 'selected' : '';
                $isArticle = $tagTemplate->getModule() === 'article' ? 'selected' : '';

                $natureOrTypeOptions = $tagTemplate->getModule() === CategoryType::ARRIVAGE ?
                        Stream::from($natures)
                            ->map(fn(Nature $n) => [
                                "id" => $n->getId(),
                                "label" => $n->getLabel(),
                                "selected" => $tagTemplate->getNatures()->contains($n),
                            ])
                            ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"]) :
                        Stream::from($types)
                            ->map(fn(Type $t) => [
                                "id" => $t->getId(),
                                "label" => $t->getLabel(),
                                "selected" => $tagTemplate->getTypes()->contains($t),
                            ])
                            ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"]);
                $selectContent = Stream::from($natureOrTypeOptions)
                    ->map(function(array $n) {
                        $selected = $n['selected'] ? "selected" : '';
                        return "<option value='{$n["id"]}' {$selected}>{$n["label"]}</option>";
                    })
                    ->join("");

                $barcodeTypeInputs = Stream::from([['label' => 'Code 128', 'value' => '1', 'checked' => $tagTemplate->isBarcode()], ['label' =>  'QR Code', 'value' => '0', 'checked' => $tagTemplate->isQRcode()]])
                    ->map(function(array $inputLine) {
                        $id = 'barcodeType-'.floor(rand(0, 10000) * 1000000);
                        $checked = $inputLine['checked'] ? 'checked' : '';
                        return "
                                <input type='radio' id=".$id." name='barcodeType' class='form-control data d-none' value=".$inputLine['value']." ".$checked." content=".$inputLine['label'].">
                                <label for=".$id.">".$inputLine['label']."</label>
                            ";
                    })
                    ->join('');

                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row w-100' data-id='{$tagTemplate->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        <input type='hidden' name='tagTemplateId' class='data' value='{$tagTemplate->getId()}'/>
                    ",
                    "prefix" => "<input type='text' name='prefix' class='{$class} needed' value='{$tagTemplate->getPrefix()}' data-global-error='Préfixe'/>",
                    "barcodeType" => "<form><div class='wii-switch needed' data-title='Type détiquette' data-name='barcodeType'>$barcodeTypeInputs</div></form>",
                    "height" => "<input type='number' name='height' class='{$class} needed' value='{$tagTemplate->getHeight()}' data-global-error='Hauteur'/>",
                    "width" => "<input type='number' name='width' class='{$class} needed' value='{$tagTemplate->getWidth()}' data-global-error='Largeur'/>",
                    "module" => "<select name='module' class='{$class} needed' data-global-error='Brique'>
                        <option value='arrivage' {$isArrival}>Arrivage</option>
                        <option value='article' {$isArticle}>Article</option>
                    </select>",
                    "natureOrType" => "<select name='natureOrType' multiple data-s2='natureOrTypeSelect' data-include-params-parent='tr' data-include-params='select[name=module]' class='{$class} needed' data-global-error='Nature(s) / Type(s)'>
                        {$selectContent}
                    </select>",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$tagTemplate->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        ",
                    "prefix" => $tagTemplate->getPrefix(),
                    "barcodeType" => $tagTemplate->isBarcode() ? 'Code 128' : 'QR Code',
                    "height" => $tagTemplate->getHeight(),
                    "width" => $tagTemplate->getWidth(),
                    "module" => $tagTemplate->getModule(),
                    "natureOrType" =>
                        !$tagTemplate->getNatures()->isEmpty() ?
                            Stream::from($tagTemplate->getNatures())
                                ->map(fn(Nature $nature) => $nature->getLabel())
                                ->join(", ") :
                                ($tagTemplate->getTypes() ?
                                    Stream::from($tagTemplate->getTypes())
                                        ->map(fn(Type $type) => $type->getLabel())
                                        ->join(", ") :
                                ''),
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "prefix" => "",
            "barcodeType" => "",
            "height" => "",
            "width" => "",
            "module" => "",
            "natureOrType" => "",
        ];

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
        ]);
    }

    /**
     * @Route("/tag-template/supprimer/{entity}", name="settings_delete_tag_template", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function deleteTagTemplate(EntityManagerInterface $entityManager,
                                   TagTemplate $entity): Response {

        $entityManager->remove($entity);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La ligne a bien été supprimée",
        ]);
    }

    /**
     * @Route("/personnalisation", name="save_translations", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function saveTranslations(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslationService $translationService,
        CacheService $cacheService
    ): Response {
        if ($translations = json_decode($request->getContent(), true)) {
            $translationRepository = $entityManager->getRepository(Translation::class);
            foreach ($translations as $translation) {
                $translationObject = $translationRepository->find($translation['id']);
                if ($translationObject) {
                    $translationObject
                        ->setTranslation($translation['val'] ?: null)
                        ->setUpdated(1);
                } else {
                    return new JsonResponse(false);
                }
            }
            $entityManager->flush();

            $cacheService->clear();
            $translationService->generateTranslationsFile();
            $translationService->cacheClearWarmUp();

            return new JsonResponse(true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/trigger-reminder-emails", name="trigger_reminder_emails", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function triggerReminderEmails(EntityManagerInterface $manager, PackService $packService): Response {
        try {
            $packService->launchPackDeliveryReminder($manager);
            $response = [
                'success' => true,
                'msg' => "Les mails de relance ont bien été envoyés",
            ];
        } catch (Throwable) {
            $response = [
                'success' => false,
                'msg' => "Une erreur est survenue lors de l'envoi des mails de relance",
            ];
        }

        return $this->json($response);
    }

    /**
     * @Route("/delete-row/{type}/{id}", name="settings_delete_row", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function deleteRow(EntityManagerInterface $manager, SettingsService $service, string $type, int $id): Response {
        try {
            match($type) {
                "timeSlots" => $service->deleteTimeSlot($manager->find(CollectTimeSlot::class, $id)),
                "startingHours" => $service->deleteStartingHour($manager->find(TransportRoundStartingHour::class, $id)),
            };

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "L'enregistrement a bien été supprimé",
            ]);
        } catch(Throwable $e) {
            return $this->json([
                "success" => false,
                "msg" => $e->getMessage(),
            ]);
        }
    }

    /**
     * @Route("/native-countries-api", name="settings_native_countries_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_ARTI}, mode=HasPermission::IN_JSON)
     */
    public function nativeCountriesApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $data = [];
        $nativeCountryRepository = $manager->getRepository(NativeCountry::class);

        foreach ($nativeCountryRepository->findAll() as $nativeCountry) {
            if ($edit) {
                $isActive = $nativeCountry->isActive() == 1 ? 'checked' : "";
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row w-50' data-id='{$nativeCountry->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        <input type='hidden' name='nativeCountryId' class='data' value='{$nativeCountry->getId()}'/>",
                    "code" => "<input type='text' name='code' class='form-control data needed' value='{$nativeCountry->getCode()}' data-global-error='Code'/>",
                    "label" => "<input type='text' name='label' class='form-control data needed' value='{$nativeCountry->getLabel()}' data-global-error='Libellé'/>",
                    "active" => "<div class='checkbox-container'><input type='checkbox' name='active' class='form-control data' {$isActive}/></div>",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$nativeCountry->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    ",
                    "code" => $nativeCountry->getCode(),
                    "label" => $nativeCountry->getLabel(),
                    "active" => $nativeCountry->isActive() ? "Oui" : "Non",
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "code" => "",
            "label" => "",
            "active" => "",
        ];

        return $this->json([
            "data" => $data,
        ]);
    }

    /**
     * @Route("/native-countries/supprimer/{entity}", name="settings_delete_native_country", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_ARTI}, mode=HasPermission::IN_JSON)
     */
    public function deleteNativeCountry(EntityManagerInterface $entityManager, NativeCountry $entity): Response {
        $articleRepository = $entityManager->getRepository(Article::class);
        if (!$articleRepository->findOneBy(["nativeCountry" => $entity])) {
            $entityManager->remove($entity);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La ligne a bien été supprimée",
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Ce pays d'origine est lié à des articles. Vous ne pouvez pas le supprimer.",
            ]);
        }
    }
}
