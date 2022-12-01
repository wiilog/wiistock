<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\FiltreRef;
use App\Entity\FreeField;
use App\Entity\Import;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\KioskToken;
use App\Entity\Language;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Statut;
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
use App\Helper\FormatHelper;
use App\Repository\IOT\AlertTemplateRepository;
use App\Repository\IOT\RequestTemplateRepository;
use App\Repository\SettingRepository;
use App\Repository\TypeRepository;
use App\Service\AttachmentService;
use App\Service\CacheService;
use App\Service\InventoryService;
use App\Service\LanguageService;
use App\Service\PackService;
use App\Service\SettingsService;
use App\Service\SpecificService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use RuntimeException;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Twig\Environment;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage")
 */
class SettingsController extends AbstractController {

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public SpecificService $specificService;

    #[Required]
    public Environment $twig;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public StatusService $statusService;

    #[Required]
    public SettingsService $settingsService;

    #[Required]
    public LanguageService $languageService;

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
                        self::MENU_TYPES_FREE_FIELDS => [
                            "label" => "Types et champs libres",
                            "wrapped" => false,
                        ],
                    ],
                ],
                self::MENU_TOUCH_TERMINAL => [
                    "label" => "Borne tactile",
                    "right" => Action::SETTINGS_DISPLAY_TOUCH_TERMINAL,
                    "save" => true,
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
                        self::MENU_FREQUENCIES => ["label" => "Fréquences"],
                        self::MENU_CATEGORIES => ["label" => "Catégories"],
                        self::MENU_MISSIONS_GENERATION => ["label" => "Gestion des missions"],
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
                        self::MENU_WAYBILL => [
                            "label" => "Lettre de voiture",
                            "save" => true,
                            "discard" => true,
                        ],
                        self::MENU_OVERCONSUMPTION_BILL => [
                            "label" => "Bon de surconsommation",
                            "save" => true,
                            "discard" => true,
                        ],
                    ],
                ],
                self::MENU_ARRIVALS => [
                    "label" => "Arrivages",
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
                    "label" => "Types et champs libres"
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
                    "wrapped" => false
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
                    'route' => "settings_language_index"
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
    ];

    public const CATEGORY_GLOBAL = "global";
    public const CATEGORY_STOCK = "stock";
    public const CATEGORY_TRACING = "trace";
    public const CATEGORY_TRACKING = "track";
    public const CATEGORY_MOBILE = "mobile";
    public const CATEGORY_DASHBOARDS = "dashboards";
    public const CATEGORY_IOT = "iot";
    public const CATEGORY_NOTIFICATIONS = "notifications";
    public const CATEGORY_USERS = "utilisateurs";
    public const CATEGORY_DATA = "donnees";

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
    public const MENU_INVENTORIES = "inventaires";
    public const MENU_FREQUENCIES = "frequences";
    public const MENU_CATEGORIES = "categories";
    public const MENU_MISSIONS_GENERATION = "missions";
    public const MENU_ARTICLES = "articles";
    public const MENU_RECEPTIONS = "receptions";
    public const MENU_RECEPTIONS_STATUSES = "statuts_receptions";
    public const MENU_DISPUTE_STATUSES = "statuts_litiges";
    public const MENU_DISPUTE_TYPES = "types_litiges";
    public const MENU_REQUESTS = "demandes";

    public const MENU_DISPATCHES = "acheminements";
    public const MENU_STATUSES = "statuts";
    public const MENU_FIXED_FIELDS = "champs_fixes";
    public const MENU_WAYBILL = "lettre_voiture";
    public const MENU_OVERCONSUMPTION_BILL = "bon_surconsommation";
    public const MENU_ARRIVALS = "arrivages";
    public const MENU_MOVEMENTS = "mouvements";
    public const MENU_FREE_FIELDS = "champs_libres";
    public const MENU_HANDLINGS = "services";
    public const MENU_REQUEST_TEMPLATES = "modeles_demande";

    public const MENU_TRANSPORT_REQUESTS = "demande_transport";
    public const MENU_ROUNDS = "tournees";
    public const MENU_TEMPERATURES = "temperatures";

    public const MENU_DELIVERIES = "livraisons";
    public const MENU_DELIVERY_REQUEST_TEMPLATES = "modeles_demande_livraisons";
    public const MENU_DELIVERY_TYPES_FREE_FIELDS = "types_champs_libres_livraisons";
    public const MENU_COLLECTS = "collectes";
    public const MENU_COLLECT_REQUEST_TEMPLATES = "modeles_demande_collectes";
    public const MENU_COLLECT_TYPES_FREE_FIELDS = "types_champs_libres_collectes";
    public const MENU_PURCHASE_STATUSES = "statuts_achats";

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

    public const MENU_EXPORTS_ENCODING = "exports_encodage";
    public const MENU_CSV_EXPORTS = "exports_csv";
    public const MENU_IMPORTS = "imports";
    public const MENU_INVENTORIES_IMPORTS = "imports_inventaires";

    /**
     * @Required
     */
    public SettingsService $service;

    /**
     * @Required
     */
    public UserService $userService;

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
            'checked' => $language->getSelected()
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
            ])
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
                "message" => "Cette langue ne peut pas être supprimée"
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
                "msg" => "La langue <strong>{$language->getLabel()}</strong> a bien été supprimée."
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
                $flagFile = $attachmentService->createAttachements($file);
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
            ])
            ->toArray();

        if ($checkFirst && !empty($types)) {
            $types[0]["checked"] = true;
        }

        return $types;
    }

    #[ArrayShape([
        self::CATEGORY_GLOBAL => "\Closure[]", self::CATEGORY_STOCK => "\Closure[][]",
        self::CATEGORY_TRACING => "\Closure[][]", self::CATEGORY_TRACKING => "array",
        self::CATEGORY_IOT => "\Closure[]", self::CATEGORY_DATA => "\Closure[]",
        self::CATEGORY_NOTIFICATIONS => "\Closure[]", self::CATEGORY_USERS => "\Closure[]"

    ])]
    public function customValues(EntityManagerInterface $entityManager): array {
        $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
        $temperatureRepository = $entityManager->getRepository(TemperatureRange::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationsRepository = $entityManager->getRepository(Emplacement::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $frequencyRepository = $entityManager->getRepository(InventoryFrequency::class);
        $fixedFieldRepository = $entityManager->getRepository(FieldsParam::class);
        $requestTemplateRepository = $entityManager->getRepository(RequestTemplate::class);
        $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $languageRepository = $entityManager->getRepository(Language::class);

        return [
            self::CATEGORY_GLOBAL => [
                self::MENU_CLIENT => fn() => [
                    "current_client" => $this->specificService->getAppClient(),
                ],
                self::MENU_MAIL_SERVER => fn() => [
                    "mailer_server" => $mailerServerRepository->findOneBy([]) ?? new MailerServer(),
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
                ],
                self::MENU_REQUESTS => [
                    self::MENU_DELIVERIES => fn() => [
                        "deliveryTypesCount" => $typeRepository->countAvailableForSelect(CategoryType::DEMANDE_LIVRAISON, []),
                        "deliveryTypeSettings" => json_encode($this->settingsService->getDefaultDeliveryLocationsByType($this->manager)),
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
                    self::MENU_COLLECT_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_COLLECTE),
                        'category' => CategoryType::DEMANDE_COLLECTE,
                    ],
                    self::MENU_PURCHASE_STATUSES => fn() => [
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_PURCHASE_REQUEST),
                    ],
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
                        "type" => $typeRepository->findOneByLabel(Type::LABEL_RECEPTION),
                    ],
                ],
                self::MENU_TOUCH_TERMINAL => fn() => [
                    'alreadyUnlinked' => empty($entityManager->getRepository(KioskToken::class)->findAll())
                ]
            ],
            self::CATEGORY_TRACING => [
                self::MENU_DISPATCHES => [
                    self::MENU_OVERCONSUMPTION_BILL => fn() => [
                        "types" => Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]))
                            ->map(fn(Type $type) => [
                                "value" => $type->getId(),
                                "label" => $type->getLabel(),
                            ])->toArray(),
                        "statuses" => Stream::from($statusRepository->findByCategorieName(CategorieStatut::DISPATCH))
                            ->filter(fn(Statut $status) => $status->getState() === Statut::NOT_TREATED)
                            ->map(fn(Statut $status) => [
                                "value" => $status->getId(),
                                "label" => $this->getFormatter()->status($status),
                            ])
                            ->toArray(),
                    ],
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $emergencyField = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY);
                        $businessField = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

                        return [
                            "emergency" => [
                                "field" => $emergencyField->getId(),
                                "modalType" => $emergencyField->getModalType(),
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
                                "modalType" => $emergencyField->getModalType(),
                                "elements" => Stream::from($businessField->getElements())
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
                        ];
                    },
                ],
                self::MENU_ARRIVALS => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $field = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

                        return [
                            "businessUnit" => [
                                "field" => $field->getId(),
                                "modalType" => $field->getModalType(),
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
                    self::MENU_FIXED_FIELDS => function() use ($userRepository, $typeRepository, $fixedFieldRepository) {
                        $field = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY);
                        $receiversField = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_RECEIVERS_HANDLING);
                        $types = $this->typeGenerator(CategoryType::DEMANDE_HANDLING, false);
                        return [
                            "emergency" => [
                                "field" => $field->getId(),
                                "modalType" => $field->getModalType(),
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
                                "modalType" => $receiversField->getModalType(),
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
                self::MENU_IMPORTS => fn() => [
                    "statuts" => $statusRepository->findByCategoryNameAndStatusCodes(
                        CategorieStatut::IMPORT,
                        [
                            Import::STATUS_PLANNED, Import::STATUS_IN_PROGRESS, Import::STATUS_CANCELLED,
                            Import::STATUS_FINISHED,
                        ]
                    ),
                ],
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

    /**
     * @Route("/enregistrer/champ-fixe/{field}", name="settings_save_field_param", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function saveFieldParam(Request $request, EntityManagerInterface $manager, FieldsParam $field): Response {
        if ($field->getModalType() == "FREE") {
            $field->setElements(explode(",", $request->request->get("elements")));
        } elseif ($field->getModalType() == "USER_BY_TYPE") {
            $lines = $request->request->has("lines") ? json_decode($request->request->get("lines"), true) : [];
            $elements = [];
            foreach ($lines as $line) {
                $elements[$line['handlingType']] = $line['user'];
            }
            $field->setElements($elements);
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
    public function typeHeader(Request $request, ?Type $type = null): Response {
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
            $fixedFieldRepository = $this->manager->getRepository(FieldsParam::class);

            $label = $type?->getLabel();
            $description = $type?->getDescription();
            $color = $type?->getColor() ?: "#000000";
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

            if ($categoryLabel === CategoryType::ARTICLE) {
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
                    "value" => "<input name='pushNotifications' type='checkbox' class='data form-control mt-1' $notificationsEnabled>",
                ];
            }

            if ($categoryLabel === CategoryType::DEMANDE_LIVRAISON) {
                $mailsEnabled = $type && $type->getSendMail() ? "checked" : "";

                $data[] = [
                    "label" => "Envoi d'un email au demandeur",
                    "value" => "<input name='mailRequester' type='checkbox' class='data form-control mt-1' $mailsEnabled>",
                ];
            } else {
                if ($categoryLabel === CategoryType::DEMANDE_DISPATCH) {
                    $pickLocationOption = $type && $type->getPickLocation() ? "<option value='{$type->getPickLocation()->getId()}'>{$type->getPickLocation()->getLabel()}</option>" : "";
                    $dropLocationOption = $type && $type->getDropLocation() ? "<option value='{$type->getDropLocation()->getId()}'>{$type->getDropLocation()->getLabel()}</option>" : "";

                    $data = array_merge($data, [
                        [
                            "label" => "Emplacement de prise par défaut",
                            "value" => "<select name='pickLocation' data-s2='location' data-parent='body' class='data form-control'>$pickLocationOption</select>",
                        ], [
                            "label" => "Emplacement de dépose par défaut",
                            "value" => "<select name='dropLocation' data-s2='location' data-parent='body' class='data form-control'>$dropLocationOption</select>",
                        ],
                    ]);
                }
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
                                "label" => "Désactiver", "value" => 0,
                                "checked" => !$type || !$type->isNotificationsEnabled(),
                            ],
                            [
                                "label" => "Activer", "value" => 1,
                                "checked" => $type && $type->isNotificationsEnabled() && !$type->getNotificationsEmergencies(),
                            ],
                            [
                                "label" => "Activer seulement si urgence", "value" => 2,
                                "checked" => $type && $type->isNotificationsEnabled() && $type->getNotificationsEmergencies(),
                            ],
                        ],
                    ],
                ]);

                $entity = [
                    CategoryType::DEMANDE_HANDLING => FieldsParam::ENTITY_CODE_HANDLING,
                    CategoryType::DEMANDE_DISPATCH => FieldsParam::ENTITY_CODE_DISPATCH,
                ];

                $emergencies = $fixedFieldRepository->getElements($entity[$categoryLabel], FieldsParam::FIELD_CODE_EMERGENCY);
                $emergencyValues = Stream::from($emergencies)
                    ->map(fn(string $emergency) => "<option value='$emergency' " . ($type && in_array($emergency, $type->getNotificationsEmergencies() ?? []) ? "selected" : "") . ">$emergency</option>")
                    ->join("");

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
                        "value" => "<select name='notificationEmergencies' data-s2 data-parent='body' data-no-empty-option multiple class='data form-control w-100'>$emergencyValues</select>",
                        "hidden" => !$type || !$type->isNotificationsEnabled() || !$type->getNotificationsEmergencies(),
                    ],
                ]);
            }

            if(in_array($categoryLabel, [CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT])) {
                $data[] = [
                    "label" => "Logo*",
                    "value" => $this->renderView("form_element.html.twig", [
                        "element" => "image",
                        "arguments" => [
                            "logo",
                            null,
                            true,
                            $type?->getLogo()?->getFullPath(),
                        ],
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

            if ($categoryLabel === CategoryType::ARTICLE) {
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
                    "value" => $type?->getSendMail() ? "Activées" : "Désactivées",
                ];
            }

            if ($categoryLabel === CategoryType::DEMANDE_DISPATCH) {
                $data = array_merge($data, [
                    [
                        "label" => "Emplacement de prise par défaut",
                        "value" => FormatHelper::location($type?->getPickLocation()),
                    ], [
                        "label" => "Emplacement de dépose par défaut",
                        "value" => FormatHelper::location($type?->getDropLocation()),
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

            if(in_array($categoryLabel, [CategoryType::DELIVERY_TRANSPORT, CategoryType::COLLECT_TRANSPORT])) {
                $data[] = [
                    "label" => "Logo",
                    "value" => $type?->getLogo()
                        ? "<img src='{$type?->getLogo()?->getFullPath()}' alt='Logo du type' style='max-height: 30px; max-width: 30px;'>"
                        : "",
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
                    "elements" => $freeField->getTypage() == FreeField::TYPE_LIST || $freeField->getTypage() == FreeField::TYPE_LIST_MULTIPLE
                        ? "<input type='text' name='elements' required class='$class' value='$elements'/>"
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
     * @Route("/champ-fixe/{entity}", name="settings_fixed_field_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function fixedFieldApi(Request $request, EntityManagerInterface $entityManager, string $entity): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $class = "form-control data";

        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $arrayFields = $fieldsParamRepository->findByEntityForEntity($entity);

        $rows = [];
        foreach ($arrayFields as $field) {
            $label = ucfirst($field->getFieldLabel());
            $displayedCreate = $field->isDisplayedCreate() ? "checked" : "";
            $requiredCreate = $field->isRequiredCreate() ? "checked" : "";
            $keptInMemoryDisabled = in_array($field->getFieldCode(), FieldsParam::MEMORY_UNKEEPABLE_FIELDS) ? "disabled" : "";
            $keptInMemory = !$keptInMemoryDisabled && $field->isKeptInMemory() ? "checked" : "";
            $displayedEdit = $field->isDisplayedEdit() ? "checked" : "";
            $requiredEdit = $field->isRequiredEdit() ? "checked" : "";
            $filtersDisabled = !in_array($field->getFieldCode(), FieldsParam::FILTERED_FIELDS) ? "disabled" : "";
            $editDisabled = in_array($field->getFieldCode(), FieldsParam::NOT_EDITABLE_FIELDS) ? "disabled" : "";
            $displayedFilters = !$filtersDisabled && $field->isDisplayedFilters() ? "checked" : "";

            $filterOnly = in_array($field->getFieldCode(), FieldsParam::FILTER_ONLY_FIELDS) ? "disabled" : "";

            if ($edit) {
                $labelAttributes = "class='font-weight-bold'";
                if ($field->getElements() !== null) {
                    $modal = strtolower($field->getFieldCode());
                    $labelAttributes = "class='font-weight-bold btn-link pointer' data-target='#modal-fixed-field-$modal' data-toggle='modal'";
                }

                $row = [
                    "label" => "<span $labelAttributes>$label</span> <input type='hidden' name='id' class='$class' value='{$field->getId()}'/>",
                    "displayedCreate" => "<input type='checkbox' name='displayedCreate' class='$class' $displayedCreate $filterOnly/>",
                    "displayedEdit" => "<input type='checkbox' name='displayedEdit' class='$class' $displayedEdit $filterOnly/>",
                    "requiredCreate" => "<input type='checkbox' name='requiredCreate' class='$class' $requiredCreate $filterOnly/>",
                    "requiredEdit" => "<input type='checkbox' name='requiredEdit' class='$class' $requiredEdit $filterOnly/>",
                    "displayedFilters" => "<input type='checkbox' name='displayedFilters' class='$class' $displayedFilters $filtersDisabled/>",
                ];

                if($entity === FieldsParam::ENTITY_CODE_ARRIVAGE) {
                    $row["keptInMemory"] = "<input type='checkbox' name='keptInMemory' class='$class' $keptInMemory $keptInMemoryDisabled/>";
                }

                $rows[] = $row;
            } else {
                $row = [
                    "label" => "<span class='font-weight-bold'>$label</span>",
                    "displayedCreate" => $field->isDisplayedCreate() ? "Oui" : "Non",
                    "displayedEdit" => $field->isDisplayedEdit() ? "Oui" : "Non",
                    "requiredCreate" => $field->isRequiredCreate() ? "Oui" : "Non",
                    "requiredEdit" => $field->isRequiredEdit() ? "Oui" : "Non",
                    "displayedFilters" => (in_array($field->getFieldCode(), FieldsParam::FILTERED_FIELDS) && $field->isDisplayedFilters()) ? "Oui" : "Non",
                ];

                if($entity === "arrival") {
                    $row["keptInMemory"] = $field->isKeptInMemory() ? "Oui" : "Non";
                }

                $rows[] = $row;
            }
        }

        return $this->json([
            "data" => $rows,
        ]);
    }


    private function canEdit(): bool {
        return $this->userService->hasRightFunction(Menu::PARAM, Action::EDIT);
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
    public function missionRulesForce(EntityManagerInterface $manager, InventoryService $inventoryService): Response {
        $rules = $manager->getRepository(InventoryMissionRule::class)->findAll();

        foreach($rules as $rule) {
            $inventoryService->createMission($rule);
        }

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/mission-rules-api", name="settings_mission_rules_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_INVENTORIES}, mode=HasPermission::IN_JSON)
     */
    public function missionRulesApi(Request $request, EntityManagerInterface $manager): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $data = [];
        $missionRuleRepository = $manager->getRepository(InventoryMissionRule::class);

        /** @var InventoryMissionRule $mission */
        foreach ($missionRuleRepository->findAll() as $mission) {
            if ($edit) {
                $categories = Stream::from($mission->getCategories())
                    ->map(fn(InventoryCategory $category) => "<option value='{$category->getId()}' selected>{$category->getLabel()}</option>")
                    ->join("");

                $periodicityWeeksSelected = $mission->getPeriodicityUnit() === InventoryMissionRule::WEEKS ? "selected" : "";
                $periodicityMonthsSelected = $mission->getPeriodicityUnit() === InventoryMissionRule::MONTHS ? "selected" : "";
                $durationWeeksSelected = $mission->getDurationUnit() === InventoryMissionRule::WEEKS ? "selected" : "";
                $durationMonthsSelected = $mission->getDurationUnit() === InventoryMissionRule::MONTHS ? "selected" : "";

                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row w-50' data-id='{$mission->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        <input type='hidden' name='id' class='data' value='{$mission->getId()}'/>",
                    "label" => "<input type='text' name='label' class='form-control data needed' value='{$mission->getLabel()}' data-global-error='Libellé'/>",
                    "categories" => "<select name='categories' class='form-control data needed' data-s2='inventoryCategories' multiple data-parent='body' data-global-error='Catégorie(s)'>$categories</select>",
                    "periodicity" => "
                        <div class='d-flex'>
                            <input type='text' name='periodicity' class='form-control data needed mr-1 w-50px' value='{$mission->getPeriodicity()}' data-global-error='Périodicité'/>
                            <select name='periodicityUnit' class='form-control data needed maxw-150px' data-global-error='Unité de periodicité'>
                                <option value='weeks' $periodicityWeeksSelected>semaine(s)</option>
                                <option value='months' $periodicityMonthsSelected>mois(s)</option>
                            </select>
                        </div>
                    ",
                    "duration" => "
                        <div class='d-flex'>
                            <input type='text' name='duration' class='form-control data needed mr-1 w-50px' value='{$mission->getDuration()}' data-global-error='Durée'/>
                            <select name='durationUnit' class='form-control data needed maxw-150px' data-global-error='Unité de durée'>
                                <option value='weeks' $durationWeeksSelected>semaine(s)</option>
                                <option value='months' $durationMonthsSelected>mois(s)</option>
                            </select>
                        </div>
                    ",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$mission->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                    ",
                    "label" => $mission->getLabel(),
                    "categories" => Stream::from($mission->getCategories())
                        ->map(fn(InventoryCategory $category) => $category->getLabel())
                        ->join(", "),
                    "periodicity" => $mission->getPeriodicityUnit() === InventoryMissionRule::WEEKS ? "Toutes les {$mission->getPeriodicity()} semaines" : "Tous les {$mission->getPeriodicity()} mois",
                    "duration" => $mission->getDurationUnit() === InventoryMissionRule::WEEKS ? "{$mission->getDuration()} semaine(s)" : "{$mission->getDuration()} mois",
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "label" => "",
            "categories" => "",
            "periodicity" => "",
            "duration" => "",
        ];

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
                $translationService->setFirstTranslation($manager, $type, $type->getLabel());
            }
        }
        $manager->flush();

        $html = $this->renderView('settings/modal_edit_translations_content.html.twig', [
            'lines' => $typesLitige
        ]);

        return new JsonResponse([
            'success' => true,
            'html' => $html
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
                'msg' => "Les traductions ont bien été modifiées."
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
}
