<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\FreeField;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\InventoryFrequency;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\VisibilityGroup;
use App\Entity\WorkFreeDay;
use App\Helper\FormatHelper;
use App\Service\SettingsService;
use App\Service\SpecificService;
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
use App\Entity\FieldsParam;

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
            "icon" => "accueil",
            "right" => Action::SETTINGS_GLOBAL,
            "menus" => [
                self::MENU_SITE_APPEARANCE => ["label" => "Apparence du site"],
                self::MENU_CLIENT => [
                    "label" => "Client application",
                    "env" => ['preprod'],
                ],
                self::MENU_LABELS => ["label" => "Étiquettes"],
                self::MENU_WORKING_HOURS => ["label" => "Heures travaillées"],
                self::MENU_OFF_DAYS => ["label" => "Jours non travaillés"],
                self::MENU_MAIL_SERVER => ["label" => "Serveur mail"],
            ],
        ],
        self::CATEGORY_STOCK => [
            "label" => "Stock",
            "icon" => "stock",
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
                            "wrapped" => false
                        ],
                    ],
                ],
                self::MENU_REQUESTS => ["label" => "Demandes"],
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
            "icon" => "traca",
            "right" => Action::SETTINGS_TRACKING,
            "menus" => [
                self::MENU_DISPATCHES => [
                    "label" => "Acheminements",
                    "menus" => [
                        self::MENU_CONFIGURATIONS => ["label" => "Configurations"],
                        self::MENU_STATUSES => ["label" => "Statuts"],
                        self::MENU_FIXED_FIELDS => ["label" => "Champs fixes"],
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres"],
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
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres"],
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
                        self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres"],
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
                self::MENU_PREPARATIONS => ["label" => "Préparations"],
                self::MENU_HANDLINGS => ["label" => "Services"],
                self::MENU_TRANSFERS => ["label" => "Transferts à traiter"],
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
                self::MENU_TYPES_FREE_FIELDS => ["label" => "Types et champs libres"],
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
                self::MENU_USERS => ["label" => "Utilisateurs"],
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
                    "wrapped" => false
                ],
                self::MENU_INVENTORIES_IMPORTS => [
                    "label" => "Imports d'inventaires",
                    "save" => false
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
    private const CATEGORY_USERS = "utilisateurs";
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

    private const MENU_PREPARATIONS = "preparations";
    private const MENU_VALIDATION = "validation";
    private const MENU_TRANSFERS = "transferts";

    private const MENU_FULL_SETTINGS = "parametrage_complet";

    private const MENU_TYPES_FREE_FIELDS = "types_champs_libres";

    private const MENU_PUSH_NOTIFICATIONS = "notifications_push";

    private const MENU_LANGUAGES = "langues";
    private const MENU_ROLES = "roles";
    private const MENU_USERS = "utilisateurs";

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
    public function item(string $category, string $menu, ?string $submenu = null): Response {
        if($submenu) {
            $parent = self::SETTINGS[$category]["menus"][$menu] ?? null;
            $path = "settings/$category/$menu/";
        } else {
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
                self::MENU_ALERTS => [],
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

                        $defaultValue = $this->renderView("form_element.html.twig", [
                            "element" => "switch",
                            "arguments" => [
                                "defaultValue",
                                null,
                                false,
                                [
                                    ["label" => "Oui", "value" => 1],
                                    ["label" => "Non", "value" => 0],
                                    ["label" => "Aucune", "value" => ""],
                                ],
                            ],
                        ]);

                        return [
                            "types" => $types,
                            "categories" => "<select name='category' class='form-control data'>$categories</select>",
                            "default_value" => $defaultValue,
                        ];
                    },
                ],
                self::MENU_INVENTORIES => [
                    self::MENU_CATEGORIES => fn() => [
                        "frequencyOptions" => Stream::from($frequencyRepository->findAll())
                            ->map(fn(InventoryFrequency $n) => [
                                "id" => $n->getId(),
                                "label" => $n->getLabel()
                            ])
                            ->sort(fn(array $a, array $b) => $a["label"] <=> $b["label"])
                            ->map(fn(array $n) => "<option value='{$n["id"]}'>{$n["label"]}</option>")
                            ->prepend("<option disabled selected>Sélectionnez une nature</option>")
                            ->join("")
                    ],
                ]
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
                ],
            ],
            self::CATEGORY_DATA => [
                self::MENU_IMPORTS => fn() => [
                    "statuts" => $statusRepository->findByCategoryNameAndStatusCodes(
                        CategorieStatut::IMPORT,
                        [Import::STATUS_PLANNED, Import::STATUS_IN_PROGRESS, Import::STATUS_CANCELLED, Import::STATUS_FINISHED]
                    ),
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
     * @Route("/champs-libes/{type}/header", name="settings_free_field_header", options={"expose"=true})
     */
    public function freeFieldHeader(Request $request, Type $type): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        if($edit) {
            $data = [[
                "label" => "Description",
                "value" => "<input name='description' class='data form-control' value='{$type->getDescription()}'>",
            ], [
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
            ]];
        } else {
            $data = [[
                "label" => "Description",
                "value" => $type->getDescription(),
            ], [
                "label" => "Couleur",
                "value" => "<div class='dt-type-color' style='background: {$type->getColor()}'></div>",
            ]];
        }

        return $this->json([
            "success" => true,
            "data" => $data,
        ]);
    }

    /**
     * @Route("/champs-libes/{type}", name="settings_free_field_api", options={"expose"=true})
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
                                ["label" => "Oui", "value" => 1],
                                ["label" => "Non", "value" => 0],
                                ["label" => "Aucune", "value" => ""],
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
                    "defaultValue" => $defaultValue,
                    "elements" => $freeField->getTypage() == FreeField::TYPE_LIST || $freeField->getTypage() == FreeField::TYPE_LIST_MULTIPLE
                        ? "<input type='text' name='elements' class='$class' value='$elements'/>"
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

        if($edit) {
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
     * @Route("/frequences-api", name="frequencies_api", options={"expose"=true})
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
     * @Route("/categories-api", name="categories_api", options={"expose"=true})
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
    public function deleteCategory(EntityManagerInterface $entityManager, InventoryFrequency $entity): Response {
        $entityManager->remove($entity);
        $entityManager->flush();
        return $this->json([
            "success" => true,
            "msg" => "La ligne a bien été supprimée",
        ]);
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
