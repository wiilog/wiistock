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
use App\Entity\FreeField;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\ParametrageGlobal;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use App\Helper\FormatHelper;
use App\Service\SpecificService;
use App\Service\SettingsService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage")
 */
class SettingsController extends AbstractController {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public SpecificService $specificService;

    public KernelInterface $kernel;

    public const SETTINGS = [
        self::CATEGORY_GLOBAL => [
            "label" => "Global",
            "icon" => "menu-global",
            "right" => Action::SETTINGS_GLOBAL,
            "menus" => [
                self::MENU_SITE_APPEARANCE => ["label" => "Apparence du site"],
                self::MENU_CLIENT => [
                    "label" => "Client application",
                    "env" => ["preprod"],
                ],
                self::MENU_LABELS => ["label" => "Étiquettes"],
                self::MENU_WORKING_HOURS => ["label" => "Heures travaillées"],
                self::MENU_OFF_DAYS => ["label" => "Jours non travaillés"],
                self::MENU_MAIL_SERVER => ["label" => "Serveur mail"],
            ],
        ],
        self::CATEGORY_STOCK => [
            "label" => "Stock",
            "icon" => "menu-stock",
            "right" => Action::SETTINGS_STOCK,
            "menus" => [
                self::MENU_CONFIGURATIONS => ["label" => "Configurations"],
                self::MENU_ALERTS => ["label" => "Alertes"],
                self::MENU_ARTICLES => [
                    "label" => "Articles",
                    "menus" => [
                        self::MENU_LABELS => ["label" => "Étiquettes"],
                        self::MENU_TYPES_FREE_FIELDS => [
                            "label" => "Types et champs libres",
                            "wrapped" => false,
                        ],
                    ],
                ],
                self::MENU_REQUESTS => [
                    "label" => "Demandes",
                    "menus" => [
                        self::MENU_DELIVERIES => ["label" => "Livraisons"],
                        self::MENU_DELIVERY_REQUEST_TEMPLATES => ["label" => "Livraisons - Modèle de demande"],
                        self::MENU_DELIVERY_TYPES_FREE_FIELDS => ["label" => "Livraisons - Types et champs libres", "wrapped" => false],
                        self::MENU_COLLECTS => ["label" => "Collectes"],
                        self::MENU_COLLECT_REQUEST_TEMPLATES => ["label" => "Collectes - Modèle de demande"],
                        self::MENU_COLLECT_TYPES_FREE_FIELDS => ["label" => "Collectes - Types et champs libres", "wrapped" => false],
                        self::MENU_PURCHASE_STATUSES => ["label" => "Achats - Statut"],
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
                        self::MENU_RECEPTIONS_STATUSES => ["label" => "Réceptions - Statuts"],
                        self::MENU_RECEPTIONS_FIXED_FIELDS => ["label" => "Réceptions - Champs fixes"],
                        self::MENU_RECEPTIONS_FREE_FIELDS => ["label" => "Réceptions - Champs libres"],
                        self::MENU_DISPUTE_STATUSES => ["label" => "Litiges - Statuts"],
                        self::MENU_DISPUTE_TYPES => ["label" => "Litiges - Types"],
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
                        self::MENU_CONFIGURATIONS => ["label" => "Configurations"],
                        self::MENU_STATUSES => ["label" => "Statuts"],
                        self::MENU_FIXED_FIELDS => ["label" => "Champs fixes"],
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres", "wrapped" => false],
                        self::MENU_WAYBILL => ["label" => "Lettre de voiture"],
                        self::MENU_OVERCONSUMPTION_BILL => ["label" => "Bon de surconsommation"],
                    ],
                ],
                self::MENU_ARRIVALS => [
                    "label" => "Arrivages",
                    "menus" => [
                        self::MENU_CONFIGURATIONS => ["label" => "Configurations"],
                        self::MENU_LABELS => ["label" => "Étiquettes"],
                        self::MENU_STATUSES => ["label" => "Statuts"],
                        self::MENU_FIXED_FIELDS => ["label" => "Champs fixes"],
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres", "wrapped" => false],
                        self::MENU_DISPUTE_STATUSES => ["label" => "Litiges - Statuts"],
                    ],
                ],
                self::MENU_MOVEMENTS => [
                    "label" => "Mouvements",
                    "menus" => [
                        self::MENU_FREE_FIELDS => ["label" => "Champs libres"],
                    ],
                ],
                self::MENU_HANDLINGS => [
                    "label" => "Services",
                    "menus" => [
                        self::MENU_STATUSES => ["label" => "Statuts"],
                        self::MENU_FIXED_FIELDS => ["label" => "Champs fixes"],
                        self::MENU_REQUEST_TEMPLATES => ["label" => "Modèles de demande"],
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
                self::MENU_DISPATCHES => ["label" => "Acheminements"],
                self::MENU_HANDLINGS => ["label" => "Services"],
                self::MENU_TRANSFERS => ["label" => "Transferts à traiter"],
                self::MENU_PREPARATIONS => ["label" => "Préparations"],
                self::MENU_VALIDATION => ["label" => "Gestion des validations"],
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
                self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres", "wrapped" => false],
            ],
        ],
        self::CATEGORY_NOTIFICATIONS => [
            "label" => "Modèles de notifications",
            "icon" => "menu-notification",
            "right" => Action::SETTINGS_NOTIFICATIONS,
            "menus" => [
                self::MENU_ALERTS => ["label" => "Alertes"],
                self::MENU_PUSH_NOTIFICATIONS => ["label" => "Notifications push"],
            ],
        ],
        self::CATEGORY_USERS => [
            "label" => "Utilisateurs",
            "icon" => "user",
            "right" => Action::SETTINGS_USERS,
            "menus" => [
                self::MENU_LANGUAGES => [
                    "label" => "Langues",
                    "route" => "settings_language",
                ],
                self::MENU_USERS => [
                    "label" => "Utilisateurs",
                    "save" => false
                ],
                self::MENU_ROLES => ["label" => "Rôles"],
            ],
        ],
        self::CATEGORY_DATA => [
            "label" => "Données",
            "icon" => "menu-donnees",
            "right" => Action::SETTINGS_DATA,
            "menus" => [
                self::MENU_CSV_EXPORTS => ["label" => "Exports CSV"],
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
    private const CATEGORY_STOCK = "stock";
    private const CATEGORY_TRACKING = "trace";
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
    private const MENU_MAIL_SERVER = "serveur_mail";

    private const MENU_CONFIGURATIONS = "configurations";
    private const MENU_VISIBILITY_GROUPS = "groupes_visibilite";
    private const MENU_ALERTS = "alertes";
    private const MENU_INVENTORIES = "inventaires";
    private const MENU_FREQUENCIES = "frequences";
    private const MENU_CATEGORIES = "categories";
    private const MENU_ARTICLES = "articles";
    private const MENU_RECEPTIONS = "receptions";
    private const MENU_RECEPTIONS_STATUSES = "statuts_receptions";
    private const MENU_RECEPTIONS_FIXED_FIELDS = "champs_fixes_receptions";
    private const MENU_RECEPTIONS_FREE_FIELDS = "champs_libres_receptions";
    private const MENU_DISPUTE_STATUSES = "statuts_litiges";
    private const MENU_DISPUTE_TYPES = "types_litiges";
    private const MENU_REQUESTS = "demandes";

    private const MENU_DISPATCHES = "acheminements";
    private const MENU_STATUSES = "statuts";
    private const MENU_FIXED_FIELDS = "champs_fixes";
    private const MENU_WAYBILL = "lettre_voiture";
    private const MENU_OVERCONSUMPTION_BILL = "bon_surconsommation";
    private const MENU_ARRIVALS = "arrivages";
    private const MENU_MOVEMENTS = "mouvements";
    private const MENU_FREE_FIELDS = "champs_libres";
    private const MENU_HANDLINGS = "services";
    private const MENU_REQUEST_TEMPLATES = "modeles_demande";

    private const MENU_DELIVERIES = "livraisons";
    private const MENU_DELIVERY_REQUEST_TEMPLATES = "modeles_demande_livraisons";
    private const MENU_DELIVERY_TYPES_FREE_FIELDS = "types_champs_libres_livraisons";
    private const MENU_COLLECTS = "collectes";
    private const MENU_COLLECT_REQUEST_TEMPLATES = "modeles_demande_collectes";
    private const MENU_COLLECT_TYPES_FREE_FIELDS = "types_champs_libres_collectes";
    private const MENU_PURCHASE_STATUSES = "statuts_achats";

    private const MENU_PREPARATIONS = "preparations";
    private const MENU_VALIDATION = "validation";
    private const MENU_TRANSFERS = "transferts";

    private const MENU_FULL_SETTINGS = "parametrage_complet";

    private const MENU_TYPES_FREE_FIELDS = "types_champs_libres";

    private const MENU_PUSH_NOTIFICATIONS = "notifications_push";

    private const MENU_LANGUAGES = "langues";
    private const MENU_ROLES = "roles";
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
    public function language(): Response {
        return $this->render("settings/utilisateurs/langues.html.twig");
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

        if(!$parent) {
            throw new NotFoundHttpException();
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

    public function customValues(): array {
        $mailerServerRepository = $this->manager->getRepository(MailerServer::class);
        $typeRepository = $this->manager->getRepository(Type::class);
        $statusRepository = $this->manager->getRepository(Statut::class);
        $freeFieldRepository = $this->manager->getRepository(FreeField::class);
        $frequencyRepository = $this->manager->getRepository(InventoryFrequency::class);
        $fixedFieldRepository = $this->manager->getRepository(FieldsParam::class);

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
                        "deliveryTypeSettings" => json_encode($this->getDefaultDeliveryLocationsByType($this->manager)),
                    ],
                    self::MENU_DELIVERY_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                        $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]))
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
                    self::MENU_COLLECT_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                        $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]))
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
                self::MENU_INVENTORIES => [
                    self::MENU_CATEGORIES => fn() => [
                        "frequencyOptions" => Stream::from($frequencyRepository->findAll())
                            ->map(fn(InventoryFrequency $n) => [
                                "id" => $n->getId(),
                                "label" => $n->getLabel(),
                            ])
                            ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                            ->map(fn(array $n) => "<option value='{$n["id"]}'>{$n["label"]}</option>")
                            ->prepend("<option disabled selected>Sélectionnez une nature</option>")
                            ->join(""),
                    ],
                ],
            ],
            self::CATEGORY_TRACKING => [
                self::MENU_DISPATCHES => [
                    self::MENU_OVERCONSUMPTION_BILL => fn() => [
                        "types" => Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]))
                            ->map(fn(Type $type) => [
                                "value" => $type->getId(),
                                "text" => $type->getLabel(),
                            ])->toArray(),
                        "statuses" => Stream::from($statusRepository->findByCategorieName(CategorieStatut::DISPATCH))
                            ->filter(fn(Statut $status) => $status->getState() === Statut::NOT_TREATED)
                            ->map(fn(Statut $status) => [
                                "value" => $status->getId(),
                                "text" => $status->getNom(),
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
                                        "text" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                            "businessUnit" => [
                                "field" => $businessField->getId(),
                                "elements" => Stream::from($businessField->getElements())
                                    ->map(fn(string $element) => [
                                        "text" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                        $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]))
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
                self::MENU_ARRIVALS => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $field = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_ARRIVAGE, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

                        return [
                            "businessUnit" => [
                                "field" => $field->getId(),
                                "elements" => Stream::from($field->getElements())
                                    ->map(fn(string $element) => [
                                        "text" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                        $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]))
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
                self::MENU_HANDLINGS => [
                    self::MENU_FIXED_FIELDS => function() use ($fixedFieldRepository) {
                        $field = $fixedFieldRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY);

                        return [
                            "emergency" => [
                                "field" => $field->getId(),
                                "elements" => Stream::from($field->getElements())
                                    ->map(fn(string $element) => [
                                        "text" => $element,
                                        "value" => $element,
                                        "selected" => true,
                                    ])
                                    ->toArray(),
                            ],
                        ];
                    },
                    self::MENU_TYPES_FREE_FIELDS => function() use ($typeRepository) {
                        $types = Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]))
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
                self::MENU_MOVEMENTS => [
                    self::MENU_FREE_FIELDS => fn() => [
                        "type" => $typeRepository->findOneByLabel(Type::LABEL_MVT_TRACA),
                    ]
                ]
            ],
            self::CATEGORY_DATA => [
                self::MENU_IMPORTS => fn() => [
                    "statuts" => $statusRepository->findByCategoryNameAndStatusCodes(
                        CategorieStatut::IMPORT,
                        [Import::STATUS_PLANNED, Import::STATUS_IN_PROGRESS, Import::STATUS_CANCELLED, Import::STATUS_FINISHED]
                    ),
                ],
            ],
            self::CATEGORY_USERS => [
                self::MENU_USERS => fn() => [
                    "newUser" => new Utilisateur()
                ]
            ]
        ];
    }

    /**
     * @Route("/enregistrer", name="settings_save", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function save(Request $request): Response {
        try {
            $this->service->save($request);
        } catch(RuntimeException $exception) {
            return $this->json([
                "success" => false,
                "msg" => $exception->getMessage(),
            ]);
        }

        return $this->json([
            "success" => true,
            "msg" => "Les nouveaux paramétrages ont été enregistrés",
        ]);
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
                    "day" => FormatHelper::longDate($day->getDay()),
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
     * @Route("/champs-libes/{type}/header", name="settings_type_header", options={"expose"=true})
     */
    public function typeHeader(Request $request, Type $type): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $category = $type->getCategory()->getLabel();

        if($edit) {
            $fixedFieldRepository = $this->manager->getRepository(FieldsParam::class);

            $data = [
                [
                    "label" => "Libellé*",
                    "value" => "<input name='label' class='data form-control' required value='{$type->getLabel()}'>",
                ],
                [
                    "label" => "Description",
                    "value" => "<input name='description' class='data form-control' value='{$type->getDescription()}'>",
                ]
            ];

            if($category === CategoryType::ARTICLE) {
                $data[] = [
                    "label" => "Couleur",
                    "value" => "
                    <input type='color' class='form-control wii-color-picker data' name='color' value='{$type->getColor()}' list='type-color-{$type->getId()}'/>
                    <datalist id='type-color-{$type->getId()}'>
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
                $notificationsEnabled = $type->isNotificationsEnabled() ? "checked" : "";

                $data[] = [
                    "label" => "Notifications push",
                    "value" => "<input name='pushNotifications' type='checkbox' class='data form-control mt-1' $notificationsEnabled>",
                ];
            }

            if($category === CategoryType::DEMANDE_DISPATCH) {
                $pickLocationOption = $type->getPickLocation() ? "<option value='{$type->getPickLocation()->getId()}'>{$type->getPickLocation()->getLabel()}</option>" : "";
                $dropLocationOption = $type->getDropLocation() ? "<option value='{$type->getDropLocation()->getId()}'>{$type->getDropLocation()->getLabel()}</option>" : "";

                $data = array_merge($data, [
                    [
                        "label" => "Emplacement de prise par défaut",
                        "value" => "<select name='pickLocation' data-s2='location' class='data form-control'>$pickLocationOption</select>",
                    ],
                    [
                        "label" => "Emplacement de dépose par défaut",
                        "value" => "<select name='dropLocation' data-s2='location' class='data form-control'>$dropLocationOption</select>",
                    ]
                ]);
            }

            if(in_array($category, [CategoryType::DEMANDE_HANDLING, CategoryType::DEMANDE_DISPATCH])) {
                $pushNotifications = $this->renderView("form_element.html.twig", [
                    "element" => "radio",
                    "arguments" => [
                        "pushNotifications",
                        "Notifications push",
                        false,
                        [
                            ["label" => "Désactiver", "value" => 0, "checked" => !$type->isNotificationsEnabled()],
                            ["label" => "Activer", "value" => 1, "checked" => $type->isNotificationsEnabled() && !$type->getNotificationsEmergencies()],
                            ["label" => "Activer seulement si urgence", "value" => 2, "checked" => $type->isNotificationsEnabled() && $type->getNotificationsEmergencies()],
                        ],
                    ],
                ]);

                $entity = [
                    CategoryType::DEMANDE_HANDLING => FieldsParam::ENTITY_CODE_HANDLING,
                    CategoryType::DEMANDE_DISPATCH => FieldsParam::ENTITY_CODE_DISPATCH,
                ];

                $emergencies = $fixedFieldRepository->getElements($entity[$category], FieldsParam::FIELD_CODE_EMERGENCY);
                $emergencyValues = Stream::from($emergencies)
                    ->map(fn(string $emergency) => "<option value='$emergency' " . (in_array($emergency, $type->getNotificationsEmergencies() ?? []) ? "selected" : "") . ">$emergency</option>")
                    ->join("");

                $data = array_merge($data, [
                    [
                        "label" => "Notifications push",
                        "value" => $pushNotifications,
                    ],
                    [
                        "label" => "Pour les valeurs",
                        "value" => "<select name='notificationEmergencies' data-s2 data-no-empty-option multiple class='data form-control w-100'>$emergencyValues</select>",
                        "hidden" => !$type->isNotificationsEnabled() || !$type->getNotificationsEmergencies(),
                    ]
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
                $data[] = [
                    "label" => "Notifications push",
                    "value" => !$type->isNotificationsEnabled()
                        ? "Désactivées"
                        : ($type->getNotificationsEmergencies()
                            ? "Activées seulement si urgence"
                            : "Activées"),
                ];

                if($type->isNotificationsEnabled() && $type->getNotificationsEmergencies()) {
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
    public function freeFieldApi(Request $request, EntityManagerInterface $manager, Type $type): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $class = "form-control data";

        $categorieCLRepository = $manager->getRepository(CategorieCL::class);
        $categories = Stream::from($categorieCLRepository->findByLabel([CategorieCL::ARTICLE, CategorieCL::REFERENCE_ARTICLE]))
            ->map(fn(CategorieCL $category) => "<option value='{$category->getId()}'>{$category->getLabel()}</option>")
            ->join("");

        $rows = [];
        foreach($type->getChampsLibres() as $freeField) {
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
                        ? "" : ($freeField->getDefaultValue() ? "Oui" : "Non");
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
                        <button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>",
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
                    "actions" => "<button class='btn btn-silent delete-row'><i class='wii-icon wii-icon-trash text-primary'></i></button>",
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

        if($edit || $type->getCategory()->getLabel() === CategoryType::MOUVEMENT_TRACA) {
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
                $isPermament = $category->getPermanent() == 1 ? 'checked' : "";
                $selectedFrequency = $category->getFrequency()->getLabel();
                $emptySelected = empty($selectedFrequency) ? 'selected' : '';
                $frequencySelectContent = Stream::from($frequencyOptions)
                    ->map(function(array $n) use ($selectedFrequency) {
                        $selected = $n['label'] === $selectedFrequency ? "selected" : '';
                        return "<option value='{$n["id"]}' {$selected}>{$n["label"]}</option>";
                    })
                    ->prepend("<option disabled {$emptySelected}>Sélectionnez une nature</option>")
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
                    "permanent" => "<div class='checkbox-container'><input type='checkbox' name='permanent' class='{$class}' {$isPermament}/></div>",
                ];
            } else {
                $data[] = [
                    "actions" => "
                        <button class='btn btn-silent delete-row' data-id='{$category->getId()}'>
                            <i class='wii-icon wii-icon-trash text-primary'></i>
                        </button>
                        ",
                    "label" => $category->getLabel(),
                    "frequency" => $category->getFrequency()->getLabel(),
                    "permanent" => $category->getPermanent() ? "Oui" : "Non",
                ];
            }
        }

        $data[] = [
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "label" => "",
            "frequency" => "",
            "permanent" => "",
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
        $entityManager->remove($entity);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La ligne a bien été supprimée",
        ]);
    }

    public function getDefaultDeliveryLocationsByType(EntityManagerInterface $entityManager): array {

        $typeRepository = $entityManager->getRepository(Type::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $defaultDeliveryLocationsParam = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON) ?? [];
        $defaultDeliveryLocationsIds = json_decode($defaultDeliveryLocationsParam, true);

        $defaultDeliveryLocations = [];
        foreach($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if($typeId !== 'all' && $typeId) {
                $type = $typeRepository->find($typeId);
            }
            if($locationId) {
                $location = $locationRepository->find($locationId);
            }

            $defaultDeliveryLocations[] = [
                'location' => isset($location)
                    ? [
                        'label' => $location->getLabel(),
                        'id' => $location->getId(),
                    ]
                    : null,
                'type' => isset($type)
                    ? [
                        'label' => $type->getLabel(),
                        'id' => $type->getId(),
                    ]
                    : null,
            ];
        }
        return $defaultDeliveryLocations;
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
        if ($visibilityGroup->getArticleReferences()->isEmpty()){
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
}
