<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use App\Helper\FormatHelper;
use App\Repository\IOT\AlertTemplateRepository;
use App\Repository\IOT\RequestTemplateRepository;
use App\Repository\TypeRepository;
use App\Service\CacheService;
use App\Service\PackService;
use App\Service\SettingsService;
use App\Service\SpecificService;
use App\Service\StatusService;
use App\Service\TranslationService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use Twig\Environment;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage")
 */
class SettingsController extends AbstractController {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public SpecificService $specificService;

    /** @Required */
    public Environment $twig;

    /** @Required */
    public KernelInterface $kernel;

    /** @Required */
    public StatusService $statusService;

    /** @Required */
    public SettingsService $settingsService;

    public const SETTINGS = [
        self::CATEGORY_GLOBAL => [
            "label" => "Global",
            "icon" => "menu-global",
            "right" => Action::SETTINGS_GLOBAL,
            "menus" => [
                self::MENU_SITE_APPEARANCE => [
                    "label" => "Apparence du site",
                    "save" => true,
                ],
                self::MENU_CLIENT => [
                    "label" => "Client application",
                    "save" => true,
                    "environment" => ["dev", "preprod"],
                ],
                self::MENU_LABELS => [
                    "label" => "Étiquettes",
                    "save" => true,
                ],
                self::MENU_WORKING_HOURS => ["label" => "Heures travaillées"],
                self::MENU_OFF_DAYS => ["label" => "Jours non travaillés"],
                self::MENU_MAIL_SERVER => [
                    "label" => "Serveur email",
                    "save" => true,
                ],
            ],
        ],
        self::CATEGORY_STOCK => [
            "label" => "Stock",
            "icon" => "menu-stock",
            "right" => Action::SETTINGS_STOCK,
            "menus" => [
                self::MENU_CONFIGURATIONS => [
                    "label" => "Configurations",
                    "save" => true,
                ],
                self::MENU_ALERTS => [
                    "label" => "Alertes",
                    "save" => true,
                ],
                self::MENU_ARTICLES => [
                    "label" => "Articles",
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
                self::MENU_REQUESTS => [
                    "label" => "Demandes",
                    "menus" => [
                        self::MENU_DELIVERIES => [
                            "label" => "Livraisons",
                            "save" => true,
                        ],
                        self::MENU_DELIVERY_REQUEST_TEMPLATES => ["label" => "Livraisons - Modèle de demande", "wrapped" => false],
                        self::MENU_DELIVERY_TYPES_FREE_FIELDS => ["label" => "Livraisons - Types et champs libres", "wrapped" => false],
                        self::MENU_COLLECTS => [
                            "label" => "Collectes",
                            "save" => true,
                        ],
                        self::MENU_COLLECT_REQUEST_TEMPLATES => ["label" => "Collectes - Modèle de demande", "wrapped" => false],
                        self::MENU_COLLECT_TYPES_FREE_FIELDS => ["label" => "Collectes - Types et champs libres", "wrapped" => false],
                        self::MENU_PURCHASE_STATUSES => ["label" => "Achats - Statuts"],
                    ],
                ],
                self::MENU_VISIBILITY_GROUPS => ["label" => "Groupes de visibilité"],
                self::MENU_INVENTORIES => [
                    "label" => "Inventaires",
                    "menus" => [
                        self::MENU_FREQUENCIES => ["label" => "Fréquences"],
                        self::MENU_CATEGORIES => ["label" => "Catégories"],
                    ],
                ],
                self::MENU_RECEPTIONS => [
                    "label" => "Réceptions",
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
        self::CATEGORY_TRACKING => [
            "label" => "Trace",
            "icon" => "menu-trace",
            "right" => Action::SETTINGS_TRACKING,
            "menus" => [
                self::MENU_DISPATCHES => [
                    "label" => "Acheminements",
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
        self::CATEGORY_MOBILE => [
            "label" => "Terminal mobile",
            "icon" => "menu-terminal-mobile",
            "right" => Action::SETTINGS_MOBILE,
            "menus" => [
                self::MENU_DISPATCHES => [
                    "label" => "Acheminements",
                    "save" => true,
                ],
                self::MENU_HANDLINGS => [
                    "label" => "Services",
                    "save" => true,
                ],
                self::MENU_TRANSFERS => [
                    "label" => "Transferts à traiter",
                    "save" => true,
                ],
                self::MENU_PREPARATIONS => [
                    "label" => "Préparations",
                    "save" => true,
                ],
                self::MENU_VALIDATION => [
                    "label" => "Gestion des validations",
                    "save" => true,
                ],
            ],
        ],
        self::CATEGORY_DASHBOARDS => [
            "label" => "Dashboards",
            "icon" => "menu-dashboard",
            "right" => Action::SETTINGS_DASHBOARDS,
            "menus" => [
                self::MENU_FULL_SETTINGS => [
                    "label" => "Paramétrage complet",
                    "route" => "dashboard_settings",
                ],
            ],
        ],
        self::CATEGORY_IOT => [
            "label" => "IoT",
            "icon" => "menu-iot",
            "right" => Action::SETTINGS_IOT,
            "menus" => [
                self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres"],
            ],
        ],
        self::CATEGORY_NOTIFICATIONS => [
            "label" => "Modèles de notifications",
            "icon" => "menu-notification",
            "right" => Action::SETTINGS_NOTIFICATIONS,
            "menus" => [
                self::MENU_ALERTS => ["label" => "Alertes", "wrapped" => false],
                self::MENU_PUSH_NOTIFICATIONS => ["label" => "Notifications push"],
            ],
        ],
        self::CATEGORY_USERS => [
            "label" => "Utilisateurs",
            "icon" => "user",
            "right" => Action::SETTINGS_USERS,
            "menus" => [
                self::MENU_LANGUAGES => [
                    "label" => "Personnalisation des libellés",
                    "save" => false,
                ],
                self::MENU_ROLES => [
                    "label" => "Rôles",
                    "save" => false,
                ],
                self::MENU_USERS => [
                    "label" => "Utilisateurs",
                    "save" => false,
                ],
            ],
        ],
        self::CATEGORY_DATA => [
            "label" => "Données",
            "icon" => "menu-donnees",
            "right" => Action::SETTINGS_DATA,
            "menus" => [
                self::MENU_CSV_EXPORTS => [
                    "label" => "Exports CSV",
                    "save" => true,
                    "discard" => true,
                ],
                self::MENU_IMPORTS => [
                    "label" => "Imports & mises à jour",
                    "save" => false,
                    "wrapped" => false,
                ],
                self::MENU_INVENTORIES_IMPORTS => [
                    "label" => "Imports d'inventaires",
                    "save" => false,
                ],
            ],
        ],
    ];

    private const CATEGORY_GLOBAL = "global";
    public const CATEGORY_STOCK = "stock";
    public const CATEGORY_TRACKING = "trace";
    private const CATEGORY_MOBILE = "mobile";
    private const CATEGORY_DASHBOARDS = "dashboards";
    private const CATEGORY_IOT = "iot";
    private const CATEGORY_NOTIFICATIONS = "notifications";
    public const CATEGORY_USERS = "utilisateurs";
    public const CATEGORY_DATA = "donnees";

    private const MENU_SITE_APPEARANCE = "apparence_site";
    private const MENU_WORKING_HOURS = "heures_travaillees";
    private const MENU_CLIENT = "client";
    private const MENU_OFF_DAYS = "jours_non_travailles";
    private const MENU_LABELS = "etiquettes";
    private const MENU_MAIL_SERVER = "serveur_email";

    private const MENU_CONFIGURATIONS = "configurations";
    private const MENU_VISIBILITY_GROUPS = "groupes_visibilite";
    private const MENU_ALERTS = "alertes";
    private const MENU_INVENTORIES = "inventaires";
    private const MENU_FREQUENCIES = "frequences";
    private const MENU_CATEGORIES = "categories";
    private const MENU_ARTICLES = "articles";
    public const MENU_RECEPTIONS = "receptions";
    private const MENU_RECEPTIONS_STATUSES = "statuts_receptions";
    public const MENU_DISPUTE_STATUSES = "statuts_litiges";
    public const MENU_DISPUTE_TYPES = "types_litiges";
    public const MENU_REQUESTS = "demandes";

    public const MENU_DISPATCHES = "acheminements";
    public const MENU_STATUSES = "statuts";
    private const MENU_FIXED_FIELDS = "champs_fixes";
    private const MENU_WAYBILL = "lettre_voiture";
    private const MENU_OVERCONSUMPTION_BILL = "bon_surconsommation";
    public const MENU_ARRIVALS = "arrivages";
    private const MENU_MOVEMENTS = "mouvements";
    private const MENU_FREE_FIELDS = "champs_libres";
    public const MENU_HANDLINGS = "services";
    private const MENU_REQUEST_TEMPLATES = "modeles_demande";

    private const MENU_DELIVERIES = "livraisons";
    private const MENU_DELIVERY_REQUEST_TEMPLATES = "modeles_demande_livraisons";
    private const MENU_DELIVERY_TYPES_FREE_FIELDS = "types_champs_libres_livraisons";
    private const MENU_COLLECTS = "collectes";
    private const MENU_COLLECT_REQUEST_TEMPLATES = "modeles_demande_collectes";
    private const MENU_COLLECT_TYPES_FREE_FIELDS = "types_champs_libres_collectes";
    public const MENU_PURCHASE_STATUSES = "statuts_achats";

    private const MENU_PREPARATIONS = "preparations";
    private const MENU_VALIDATION = "validation";
    private const MENU_TRANSFERS = "transferts";

    private const MENU_FULL_SETTINGS = "parametrage_complet";

    private const MENU_TYPES_FREE_FIELDS = "types_champs_libres";

    private const MENU_PUSH_NOTIFICATIONS = "notifications_push";

    private const MENU_LANGUAGES = "langues";
    public const MENU_ROLES = "roles";
    public const MENU_USERS = "utilisateurs";

    private const MENU_CSV_EXPORTS = "exports_csv";
    public const MENU_IMPORTS = "imports";
    private const MENU_INVENTORIES_IMPORTS = "imports_inventaires";

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
     * @HasPermission({Menu::PARAM, Action::SETTINGS_USERS})
     */
    public function language(EntityManagerInterface $manager): Response {
        $translationRepository = $manager->getRepository(Translation::class);

        return $this->render("settings/utilisateurs/langues.html.twig", [
            'translations' => $translationRepository->findAll(),
            'menusTranslations' => array_column($translationRepository->getMenus(), '1')
        ]);
    }

    /**
     * @Route("/afficher/{category}/{menu}/{submenu}", name="settings_item", options={"expose"=true})
     */
    public function item(string $category, ?string $menu = null, ?string $submenu = null): Response {
        if($submenu) {
            $parent = self::SETTINGS[$category]["menus"][$menu] ?? null;
            $path = "settings/$category/$menu/";
        } else {
            $menu = $menu ?? array_key_first(self::SETTINGS[$category]["menus"]);

            // contains sub menus
            if(isset(self::SETTINGS[$category]["menus"][$menu]['menus'])) {
                $parent = self::SETTINGS[$category]["menus"][$menu] ?? null;
                $submenu = array_key_first($parent["menus"]);

                $path = "settings/$category/$menu/";
            } else {
                $parent = self::SETTINGS[$category] ?? null;
                $path = "settings/$category/";
            }
        }

        if(!$parent
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
            "values" => $this->customValues(),
        ]);
    }

    private function typeGenerator(string $category, $checkFirst = true): array {
        $typeRepository = $this->manager->getRepository(Type::class);
        $types = Stream::from($typeRepository->findByCategoryLabels([$category]))
            ->map(fn(Type $type) => [
                "label" => $type->getLabel(),
                "value" => $type->getId(),
            ])
            ->toArray();

        if ($checkFirst && !empty($types)) {
            $types[0]["checked"] = true;
        }

        return $types;
    }

    public function customValues(): array {
        $mailerServerRepository = $this->manager->getRepository(MailerServer::class);
        $typeRepository = $this->manager->getRepository(Type::class);
        $statusRepository = $this->manager->getRepository(Statut::class);
        $freeFieldRepository = $this->manager->getRepository(FreeField::class);
        $frequencyRepository = $this->manager->getRepository(InventoryFrequency::class);
        $fixedFieldRepository = $this->manager->getRepository(FieldsParam::class);
        $requestTemplateRepository = $this->manager->getRepository(RequestTemplate::class);
        $alertTemplateRepository = $this->manager->getRepository(AlertTemplate::class);
        $translationRepository = $this->manager->getRepository(Translation::class);

        return [
            self::CATEGORY_GLOBAL => [
                self::MENU_CLIENT => fn() => [
                    "current_client" => $this->specificService->getAppClient(),
                ],
                self::MENU_MAIL_SERVER => fn() => [
                    "mailer_server" => $mailerServerRepository->findOneBy([]),
                ],
            ],
            self::CATEGORY_STOCK => [
                self::MENU_ARTICLES => [
                    self::MENU_LABELS => fn() => [
                        "free_fields" => Stream::from($freeFieldRepository->findByCategory(CategorieCL::ARTICLE))
                            ->keymap(fn(FreeField $field) => [$field->getLabel(), $field->getLabel()])
                            ->toArray(),
                    ],
                    self::MENU_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                        $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::ARTICLE]))
                            ->map(fn(Type $type) => [
                                "label" => $type->getLabel(),
                                "value" => $type->getId(),
                            ])
                            ->toArray();

                        $types[0]["checked"] = true;

                        $categorieCLRepository = $this->manager->getRepository(CategorieCL::class);
                        $categories = Stream::from($categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]))
                            ->map(fn(CategorieCL $category) => "<option value='{$category->getId()}'>{$category->getLabel()}</option>")
                            ->join("");

                        return [
                            "types" => $types,
                            "categories" => "<select name='category' class='form-control data'>$categories</select>",
                        ];
                    },
                ],
                self::MENU_REQUESTS => [
                    self::MENU_DELIVERIES => fn() => [
                        "deliveryTypesCount" => $typeRepository->countAvailableForSelect(CategoryType::DEMANDE_LIVRAISON, []),
                        "deliveryTypeSettings" => json_encode($this->settingsService->getDefaultDeliveryLocationsByType($this->manager)),
                    ],
                    self::MENU_DELIVERY_REQUEST_TEMPLATES => function() use ($requestTemplateRepository, $typeRepository) {
                        return $this->getRequestTemplates($typeRepository, $requestTemplateRepository, Type::LABEL_DELIVERY);
                    },
                    self::MENU_COLLECT_REQUEST_TEMPLATES => function() use ($requestTemplateRepository, $typeRepository) {
                        return $this->getRequestTemplates($typeRepository, $requestTemplateRepository, Type::LABEL_COLLECT);
                    },
                    self::MENU_DELIVERY_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_LIVRAISON)
                    ],
                    self::MENU_COLLECT_TYPES_FREE_FIELDS => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_COLLECTE),
                    ],
                    self::MENU_PURCHASE_STATUSES => fn() => [
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_PURCHASE_REQUEST),
                    ],
                ],
                self::MENU_INVENTORIES => [
                    self::MENU_CATEGORIES => fn() => [
                        "frequencyOptions" => Stream::from($frequencyRepository->findAll())
                            ->map(fn(InventoryFrequency $n) => [
                                "id" => $n->getId(),
                                "label" => $n->getLabel(),
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
                ]
            ],
            self::CATEGORY_TRACKING => [
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
                                "label" => $status->getNom(),
                            ])->toArray(),
                    ],
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $emergencyField = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_EMERGENCY);
                        $businessField = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DISPATCH, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

                        return [
                            "emergency" => [
                                "field" => $emergencyField->getId(),
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
                    ],
                    self::MENU_STATUSES => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_DISPATCH, false),
                        'categoryType' => CategoryType::DEMANDE_DISPATCH,
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_DISPATCH),
                    ],
                ],
                self::MENU_ARRIVALS => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $field = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

                        return [
                            "businessUnit" => [
                                "field" => $field->getId(),
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
                    ],
                    self::MENU_DISPUTE_STATUSES => fn() => [
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_ARRIVAL_DISPUTE),
                    ],
                    self::MENU_STATUSES => fn() => [
                        'types' => $this->typeGenerator(CategoryType::ARRIVAGE, false),
                        'categoryType' => CategoryType::ARRIVAGE,
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_ARRIVAL),
                    ],
                ],
                self::MENU_HANDLINGS => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $field = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY);

                        return [
                            "emergency" => [
                                "field" => $field->getId(),
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
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_HANDLING),
                    ],
                    self::MENU_REQUEST_TEMPLATES => function() use ($requestTemplateRepository, $typeRepository) {
                        return $this->getRequestTemplates($typeRepository, $requestTemplateRepository, Type::LABEL_HANDLING);
                    },
                    self::MENU_STATUSES => fn() => [
                        'types' => $this->typeGenerator(CategoryType::DEMANDE_HANDLING, false),
                        'categoryType' => CategoryType::DEMANDE_HANDLING,
                        'optionsSelect' => $this->statusService->getStatusStatesOptions(StatusController::MODE_HANDLING),
                    ],
                ],
                self::MENU_MOVEMENTS => [
                    self::MENU_FREE_FIELDS => fn() => [
                        "type" => $typeRepository->findOneByLabel(Type::LABEL_MVT_TRACA),
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
                self::MENU_IMPORTS => fn() => [
                    "statuts" => $statusRepository->findByCategoryNameAndStatusCodes(
                        CategorieStatut::IMPORT,
                        [Import::STATUS_PLANNED, Import::STATUS_IN_PROGRESS, Import::STATUS_CANCELLED, Import::STATUS_FINISHED]
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
                ],
                self::MENU_LANGUAGES => fn() => [
                    'translations' => $translationRepository->findAll(),
                    'menusTranslations' => array_column($translationRepository->getMenus(), '1')
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
        } catch(RuntimeException $exception) {
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
        $field->setElements(explode(",", $request->request->get("elements")));
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Les nouveaux paramétrages du champ ont été enregistrés",
        ]);
    }

    /**
     * @Route("/heures-travaillees-api", name="settings_working_hours_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL})
     */
    public function workingHoursApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $daysWorkedRepository = $manager->getRepository(DaysWorked::class);

        $data = [];
        foreach($daysWorkedRepository->findAll() as $day) {
            if($edit) {
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
     * @Route("/jours-non-travailles-api", name="settings_off_days_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL})
     */
    public function offDaysApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $data = [];
        if(!$edit) {
            $workFreeDayRepository = $manager->getRepository(WorkFreeDay::class);

            foreach($workFreeDayRepository->findAll() as $day) {
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
     * @Route("/champs-libes/header/{type}", name="settings_type_header", options={"expose"=true})
     */
    public function typeHeader(Request $request, ?Type $type = null): Response {
        $typeRepository = $this->manager->getRepository(Type::class);

        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $category = $typeRepository->getEntities($request->request->get("types"));

        if(count($category) !== 1) {
            return $this->json([
                "success" => false,
                "msg" => "Configuration invalide, les types ne peuvent pas être récupérés",
            ]);
        } else {
            $category = $category[0];
        }

        if($edit) {
            $fixedFieldRepository = $this->manager->getRepository(FieldsParam::class);

            $label = $type?->getLabel();
            $description = $type?->getDescription();
            $color = $type ? $type->getColor() : "#000000";

            $data = [
                [
                    "type" => "hidden",
                    "name" => "entity",
                    "class" => "category",
                    "value" => $category,
                ],
                [
                    "label" => "Libellé*",
                    "value" => "<input name='label' class='data form-control' required value='$label'>",
                ],
                [
                    "label" => "Description",
                    "value" => "<input name='description' class='data form-control' value='$description'>",
                ]
            ];

            if($category === CategoryType::ARTICLE) {
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

            if(in_array($category, [CategoryType::DEMANDE_LIVRAISON, CategoryType::DEMANDE_COLLECTE])) {
                $notificationsEnabled = $type && $type->isNotificationsEnabled() ? "checked" : "";

                $data[] = [
                    "label" => "Notifications push",
                    "value" => "<input name='pushNotifications' type='checkbox' class='data form-control mt-1' $notificationsEnabled>",
                ];
            }

            if($category === CategoryType::DEMANDE_LIVRAISON) {
                $mailsEnabled = $type && $type->getSendMail() ? "checked" : "";

                $data[] = [
                    "label" => "Envoi d'un email au demandeur",
                    "value" => "<input name='mailRequester' type='checkbox' class='data form-control mt-1' $mailsEnabled>",
                ];
            }
            else if($category === CategoryType::DEMANDE_DISPATCH) {
                $pickLocationOption = $type && $type->getPickLocation() ? "<option value='{$type->getPickLocation()->getId()}'>{$type->getPickLocation()->getLabel()}</option>" : "";
                $dropLocationOption = $type && $type->getDropLocation() ? "<option value='{$type->getDropLocation()->getId()}'>{$type->getDropLocation()->getLabel()}</option>" : "";

                $data = array_merge($data, [[
                    "label" => "Emplacement de prise par défaut",
                    "value" => "<select name='pickLocation' data-s2='location' data-parent='body' class='data form-control'>$pickLocationOption</select>",
                ], [
                    "label" => "Emplacement de dépose par défaut",
                    "value" => "<select name='dropLocation' data-s2='location' data-parent='body' class='data form-control'>$dropLocationOption</select>",
                ]]);
            }

            if(in_array($category, [CategoryType::DEMANDE_HANDLING, CategoryType::DEMANDE_DISPATCH])) {
                $pushNotifications = $this->renderView("form_element.html.twig", [
                    "element" => "radio",
                    "arguments" => [
                        "pushNotifications",
                        "Notifications push",
                        false,
                        [
                            ["label" => "Désactiver", "value" => 0, "checked" => !$type || !$type->isNotificationsEnabled()],
                            ["label" => "Activer", "value" => 1, "checked" => $type && $type->isNotificationsEnabled() && !$type->getNotificationsEmergencies()],
                            ["label" => "Activer seulement si urgence", "value" => 2, "checked" => $type && $type->isNotificationsEnabled() && $type->getNotificationsEmergencies()],
                        ],
                    ],
                ]);

                $entity = [
                    CategoryType::DEMANDE_HANDLING => FieldsParam::ENTITY_CODE_HANDLING,
                    CategoryType::DEMANDE_DISPATCH => FieldsParam::ENTITY_CODE_DISPATCH,
                ];

                $emergencies = $fixedFieldRepository->getElements($entity[$category], FieldsParam::FIELD_CODE_EMERGENCY);
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
        } else {
            $data = [[
                "label" => "Description",
                "value" => $type->getDescription(),
            ]];

            if($category === CategoryType::ARTICLE) {
                $data[] = [
                    "label" => "Couleur",
                    "value" => "<div class='dt-type-color' style='background: {$type->getColor()}'></div>",
                ];
            }

            if(in_array($category, [CategoryType::DEMANDE_LIVRAISON, CategoryType::DEMANDE_COLLECTE])) {
                $data[] = [
                    "label" => "Notifications push",
                    "value" => $type->isNotificationsEnabled() ? "Activées" : "Désactivées",
                ];
            }

            if($category === CategoryType::DEMANDE_LIVRAISON) {
                $data[] = [
                    "label" => "Envoi d'un email au demandeur",
                    "value" => $type->getSendMail() ? "Activées" : "Désactivées",
                ];
            }

            if($category === CategoryType::DEMANDE_DISPATCH) {
                $data = array_merge($data, [[
                    "label" => "Emplacement de prise par défaut",
                    "value" => FormatHelper::location($type->getPickLocation()),
                ], [
                    "label" => "Emplacement de dépose par défaut",
                    "value" => FormatHelper::location($type->getDropLocation()),
                ]]);
            }

            if(in_array($category, [CategoryType::DEMANDE_HANDLING, CategoryType::DEMANDE_DISPATCH])) {
                $hasNotificationsEmergencies = $type->isNotificationsEnabled() && $type->getNotificationsEmergencies();
                if($hasNotificationsEmergencies) {
                    $data[] = [
                        "breakline" => true,
                    ];
                }

                $data[] = [
                    "label" => "Notifications push",
                    "value" => !$type->isNotificationsEnabled()
                        ? "Désactivées"
                        : ($type->getNotificationsEmergencies()
                            ? "Activées seulement si urgence"
                            : "Activées"),
                ];

                if($hasNotificationsEmergencies) {
                    $data[] = [
                        "label" => "Pour les valeurs",
                        "value" => join(", ", $type->getNotificationsEmergencies()),
                    ];
                }
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
        $categories = Stream::from($categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]))
            ->map(fn(CategorieCL $category) => "<option value='{$category->getId()}'>{$category->getLabel()}</option>")
            ->join("");

        $rows = [];
        $freeFields = $type ? $type->getChampsLibres() : [];
        foreach($freeFields as $freeField) {
            if($freeField->getTypage() === FreeField::TYPE_BOOL) {
                $typageCLFr = "Oui/Non";
            } else if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                $typageCLFr = "Nombre";
            } else if($freeField->getTypage() === FreeField::TYPE_TEXT) {
                $typageCLFr = "Texte";
            } else if($freeField->getTypage() === FreeField::TYPE_LIST) {
                $typageCLFr = "Liste";
            } else if($freeField->getTypage() === FreeField::TYPE_DATE) {
                $typageCLFr = "Date";
            } else if($freeField->getTypage() === FreeField::TYPE_DATETIME) {
                $typageCLFr = "Date et heure";
            } else if($freeField->getTypage() === FreeField::TYPE_LIST_MULTIPLE) {
                $typageCLFr = "Liste multiple";
            } else {
                $typageCLFr = "";
            }

            $defaultValue = null;
            if($freeField->getTypage() == FreeField::TYPE_BOOL) {
                if(!$edit) {
                    $defaultValue = ($freeField->getDefaultValue() === null || $freeField->getDefaultValue() === "")
                        ? ""
                        : ($freeField->getDefaultValue() ? "Oui" : "Non");
                } else {
                    if($freeField->getDefaultValue() === "") {
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
                                ["label" => "Non", "value" => 0, "checked" => $freeField->getDefaultValue() !== null && !$freeField->getDefaultValue()],
                                ["label" => "Aucune", "value" => null, "checked" => $freeField->getDefaultValue() === null],
                            ],
                        ],
                    ]);

                    $defaultValue = "<div class='wii-switch-small'>$defaultValue</div>";
                }
            } else if($freeField->getTypage() === FreeField::TYPE_DATETIME || $freeField->getTypage() === FreeField::TYPE_DATE) {
                $defaultValueDate = new DateTime(str_replace("/", "-", $freeField->getDefaultValue())) ?: null;
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

                $defaultValue = "<select name='defaultValue' class='form-control data' data-global-error='Valeur par défaut'>$options</select>";
            } else if($freeField->getTypage() !== FreeField::TYPE_LIST_MULTIPLE) {
                if(!$edit) {
                    $defaultValue = $freeField->getDefaultValue();
                } else {
                    $inputType = $freeField->getTypage() === FreeField::TYPE_NUMBER ? "number" : "text";
                    $defaultValue = "<input type='$inputType' name='defaultValue' class='$class' value='{$freeField->getDefaultValue()}'/>";
                }
            }

            if($edit) {
                $displayedCreate = $freeField->getDisplayedCreate() ? "checked" : "";
                $requiredCreate = $freeField->isRequiredCreate() ? "checked" : "";
                $requiredEdit = $freeField->isRequiredEdit() ? "checked" : "";
                $elements = join(";", $freeField->getElements());

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
            }
            else {
                $rows[] = [
                    "id" => $freeField->getId(),
                    "actions" => "<button class='btn btn-silent delete-row' data-id='{$freeField->getId()}'><i class='wii-icon wii-icon-trash text-primary'></i></button>",
                    "label" => $freeField->getLabel() ?: 'Non défini',
                    "appliesTo" => $freeField->getCategorieCL() ? ucfirst($freeField->getCategorieCL()->getLabel()) : "",
                    "type" => $typageCLFr,
                    "displayedCreate" => ($freeField->getDisplayedCreate() ? "oui" : "non"),
                    "requiredCreate" => ($freeField->isRequiredCreate() ? "oui" : "non"),
                    "requiredEdit" => ($freeField->isRequiredEdit() ? "oui" : "non"),
                    "defaultValue" => $defaultValue,
                    "elements" => $freeField->getTypage() == FreeField::TYPE_LIST || $freeField->getTypage() == FreeField::TYPE_LIST_MULTIPLE ? $this->renderView('free_field/freeFieldElems.html.twig', ['elems' => $freeField->getElements()]) : '',
                ];
            }
        }

        $typeFreePages = $type && in_array($type->getCategory()->getLabel(), [CategoryType::MOUVEMENT_TRACA, CategoryType::SENSOR, CategoryType::RECEPTION]);
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
    public function deleteFreeField(EntityManagerInterface $manager, FreeField $entity) {
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
        foreach($arrayFields as $field) {
            $label = ucfirst($field->getFieldLabel());
            $displayedCreate = $field->isDisplayedCreate() ? "checked" : "";
            $requiredCreate = $field->isRequiredCreate() ? "checked" : "";
            $displayedEdit = $field->isDisplayedEdit() ? "checked" : "";
            $requiredEdit = $field->isRequiredEdit() ? "checked" : "";
            $filtersDisabled = !in_array($field->getFieldCode(), FieldsParam::FILTERED_FIELDS) ? "disabled" : "";
            $displayedFilters = !$filtersDisabled && $field->isDisplayedFilters() ? "checked" : "";

            if($edit) {
                $labelAttributes = "class='font-weight-bold'";
                if($field->getElements() !== null) {
                    $modal = strtolower($field->getFieldCode());
                    $labelAttributes = "class='font-weight-bold btn-link pointer' data-target='#modal-fixed-field-$modal' data-toggle='modal'";
                }

                $rows[] = [
                    "label" => "<span $labelAttributes>$label</span> <input type='hidden' name='id' class='$class' value='{$field->getId()}'/>",
                    "displayedCreate" => "<input type='checkbox' name='displayedCreate' class='$class' $displayedCreate/>",
                    "displayedEdit" => "<input type='checkbox' name='displayedEdit' class='$class' $displayedEdit/>",
                    "requiredCreate" => "<input type='checkbox' name='requiredCreate' class='$class' $requiredCreate/>",
                    "requiredEdit" => "<input type='checkbox' name='requiredEdit' class='$class' $requiredEdit/>",
                    "displayedFilters" => "<input type='checkbox' name='displayedFilters' class='$class' $displayedFilters $filtersDisabled/>",
                ];
            } else {
                $rows[] = [
                    "label" => "<span class='font-weight-bold'>$label</span>",
                    "displayedCreate" => $field->isDisplayedCreate() ? "Oui" : "Non",
                    "displayedEdit" => $field->isDisplayedEdit() ? "Oui" : "Non",
                    "requiredCreate" => $field->isRequiredCreate() ? "Oui" : "Non",
                    "requiredEdit" => $field->isRequiredEdit() ? "Oui" : "Non",
                    "displayedFilters" => (in_array($field->getFieldCode(), FieldsParam::FILTERED_FIELDS) && $field->isDisplayedFilters()) ? "Oui" : "Non",
                ];
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
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK}, mode=HasPermission::IN_JSON)
     */
    public function frequenciesApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $data = [];
        $inventoryFrequencyRepository = $manager->getRepository(InventoryFrequency::class);

        foreach($inventoryFrequencyRepository->findAll() as $frequence) {
            if($edit) {
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
            "className" => "toto",
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
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK}, mode=HasPermission::IN_JSON)
     */
    public function deleteFrequency(EntityManagerInterface $entityManager, InventoryFrequency $entity): Response {
        if($entity->getCategories()->isEmpty()) {
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
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK}, mode=HasPermission::IN_JSON)
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

        foreach($inventoryCategoryRepository->findAll() as $category) {
            if($edit) {
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
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK}, mode=HasPermission::IN_JSON)
     */
    public function deleteCategory(EntityManagerInterface $entityManager, InventoryCategory $entity): Response {
        if(!$entity->getRefArticle()->isEmpty()) {
            return $this->json([
                "success" => false,
                "msg" => "La catégorie est liée à des références articles",
            ]);
        }

        if($entity->getFrequency()) {
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
     * @Route("/types-litige-api", name="types_litige_api", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK}, mode=HasPermission::IN_JSON)
     */
    public function typesLitigeApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $data = [];
        $typeRepository = $manager->getRepository(Type::class);
        $typesLitige = $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]);
        foreach($typesLitige as $type) {
            if($edit) {
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
     * @Route("/types_litiges/supprimer/{entity}", name="settings_delete_type_litige", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK}, mode=HasPermission::IN_JSON)
     */
    public function deleteTypeLitige(EntityManagerInterface $entityManager, Type $entity): Response {
        if($entity->getDisputes()->isEmpty()) {
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
     * @HasPermission({Menu::PARAM, Action::SETTINGS_STOCK})
     */
    public function visibiliteGroupApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $data = [];
        $visibilityGroupRepository = $manager->getRepository(VisibilityGroup::class);

        foreach($visibilityGroupRepository->findAll() as $visibilityGroup) {
            if($edit) {
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
        if($visibilityGroup->getArticleReferences()->isEmpty()) {
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
    public function saveTranslations(Request $request,
                                     EntityManagerInterface $entityManager,
                                     TranslationService $translationService,
                                     CacheService $cacheService): Response {
        if($translations = json_decode($request->getContent(), true)) {
            $translationRepository = $entityManager->getRepository(Translation::class);
            foreach($translations as $translation) {
                $translationObject = $translationRepository->find($translation['id']);
                if($translationObject) {
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
    public function triggerReminderEmails(EntityManagerInterface $manager, PackService $packService): Response
    {
        try {
            $packService->launchPackDeliveryReminder($manager);
            $response = [
                'success' => true,
                'msg' => "Les mails de relance ont bien été envoyés"
            ];
        } catch (Throwable) {
            $response = [
                'success' => false,
                'msg' => "Une erreur est survenue lors de l'envoi des mails de relance"
            ];
        }

        return $this->json($response);
    }
}
