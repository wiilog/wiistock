<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\DaysWorked;
use App\Entity\FreeField;
use App\Entity\Import;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
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
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\HasPermission;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage")
 */
class SettingsController extends AbstractController {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public SpecificService $specificService;

    public const SETTINGS = [
        self::CATEGORY_GLOBAL => [
            "label" => "Global",
            "icon" => "accueil",
            "right" => Action::SETTINGS_GLOBAL,
            "menus" => [
                self::MENU_SITE_APPEARANCE => "Apparence du site",
                self::MENU_CLIENT => "Client application",
                self::MENU_LABELS => "Étiquettes",
                self::MENU_WORKING_HOURS => "Heures travaillées",
                self::MENU_OFF_DAYS => "Jours non travaillés",
                self::MENU_MAIL_SERVER => "Serveur mail",
            ],
        ],
        self::CATEGORY_STOCK => [
            "label" => "Stock",
            "icon" => "stock",
            "right" => Action::SETTINGS_STOCK,
            "menus" => [
                self::MENU_CONFIGURATIONS => "Configurations",
                self::MENU_ALERTS => "Alertes",
                self::MENU_ARTICLES => [
                    "label" => "Articles",
                    "menus" => [
                        self::MENU_LABELS => "Étiquettes",
                        self::MENU_TYPES_FREE_FIELDS => "Types et champs libres",
                    ],
                ],
                self::MENU_REQUESTS => "Demandes",
                self::MENU_VISIBILITY_GROUPS => "Groupes de visibilité",
                self::MENU_INVENTORIES => [
                    "label" => "Inventaires",
                    "menus" => [
                        self::MENU_FREQUENCIES => "Fréquences",
                        self::MENU_CATEGORIES => "Catégories",
                    ],
                ],
                self::MENU_RECEPTIONS => [
                    "label" => "Réceptions",
                    "menus" => [
                        self::MENU_RECEPTIONS_STATUSES => "Réceptions - Statuts",
                        self::MENU_RECEPTIONS_FIXED_FIELDS => "Réceptions - Champs fixes",
                        self::MENU_RECEPTIONS_FREE_FIELDS => "Réceptions - Champs libres",
                        self::MENU_DISPUTE_STATUSES => "Litiges - Statuts",
                        self::MENU_DISPUTE_TYPES => "Litiges - Types",
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
                        self::MENU_CONFIGURATIONS => "Configurations",
                        self::MENU_STATUSES => "Statuts",
                        self::MENU_FIXED_FIELDS => "Champs fixes",
                        self::MENU_TYPES_FREE_FIELDS => "Types et champs libres",
                        self::MENU_WAYBILL => "Lettre de voiture",
                        self::MENU_OVERCONSUMPTION_BILL => "Bon de surconsommation",
                    ],
                ],
                self::MENU_ARRIVALS => [
                    "label" => "Arrivages",
                    "menus" => [
                        self::MENU_CONFIGURATIONS => "Configurations",
                        self::MENU_LABELS => "Étiquettes",
                        self::MENU_STATUSES => "Statuts",
                        self::MENU_FIXED_FIELDS => "Champs fixes",
                        self::MENU_TYPES_FREE_FIELDS => "Types et champs libres",
                        self::MENU_DISPUTE_STATUSES => "Litiges - Statuts",
                    ],
                ],
                self::MENU_MOVEMENTS => [
                    "label" => "Mouvements",
                    "menus" => [
                        self::MENU_FREE_FIELDS => "Champs libres",
                    ],
                ],
                self::MENU_HANDLINGS => [
                    "label" => "Services",
                    "menus" => [
                        self::MENU_STATUSES => "Statuts",
                        self::MENU_FIXED_FIELDS => "Champs fixes",
                        self::MENU_REQUEST_TEMPLATES => "Modèles de demande",
                        self::MENU_TYPES_FREE_FIELDS => "Types et champs libres",
                    ],
                ],
            ],
        ],
        self::CATEGORY_MOBILE => [
            "label" => "Terminal mobile",
            "icon" => "accueil",
            "right" => Action::SETTINGS_MOBILE,
            "menus" => [
                self::MENU_DISPATCHES => "Acheminements",
                self::MENU_PREPARATIONS => "Préparations",
                self::MENU_HANDLINGS => "Services",
                self::MENU_TRANSFERS => "Transferts à traiter",
                self::MENU_VALIDATION => "Gestion des validations",
            ],
        ],
        self::CATEGORY_DASHBOARDS => [
            "label" => "Dashboards",
            "icon" => "accueil",
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
            "icon" => "accueil",
            "right" => Action::SETTINGS_IOT,
            "menus" => [
                self::MENU_TYPES_FREE_FIELDS => "Types et champs libres",
            ],
        ],
        self::CATEGORY_NOTIFICATIONS => [
            "label" => "Modèles de notifications",
            "icon" => "accueil",
            "right" => Action::SETTINGS_NOTIFICATIONS,
            "menus" => [
                self::MENU_ALERTS => "Alertes",
                self::MENU_PUSH_NOTIFICATIONS => "Notifications push",
            ],
        ],
        self::CATEGORY_USERS => [
            "label" => "Utilisateurs",
            "icon" => "accueil",
            "right" => Action::SETTINGS_USERS,
            "menus" => [
                self::MENU_LANGUAGES => [
                    "label" => "Langues",
                    "route" => "settings_language",
                ],
                self::MENU_USERS => "Utilisateurs",
                self::MENU_ROLES => "Rôles",
            ],
        ],
        self::CATEGORY_DATA => [
            "label" => "Données",
            "icon" => "accueil",
            "right" => Action::SETTINGS_DATA,
            "menus" => [
                self::MENU_CSV_EXPORTS => "Alertes",
                self::MENU_IMPORTS => "Imports & mises à jour",
                self::MENU_INVENTORY_IMPORTS => "Imports d'inventaires",
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
    private const CATEGORY_DATA = "donnees";

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
    private const MENU_IMPORTS = "imports";
    private const MENU_INVENTORY_IMPORTS = "imports_inventaires";

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
        return $this->render("settings/utilisateurs/langues.html.twig", [
            "settings" => self::SETTINGS,
        ]);
    }

    /**
     * @Route("/afficher/{category}/{menu}/{submenu}", name="settings_item", options={"expose"=true})
     */
    public function item(string $category, string $menu, ?string $submenu = null): Response {
        if($submenu) {
            $parent = self::SETTINGS[$category]["menus"][$menu] ?? null;
            $path = "settings/$category/$menu/";
        } else {
            if(is_array(self::SETTINGS[$category]["menus"][$menu])) {
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
        $freeFieldRepository = $this->manager->getRepository(FreeField::class);
        $statusRepository = $this->manager->getRepository(Statut::class);

        return [
            self::CATEGORY_GLOBAL => [
                self::MENU_CLIENT => [
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
                                ]
                            ],
                        ]);

                        return [
                            "types" => $types,
                            "categories" => "<select name='category' class='form-control data'>$categories</select>",
                            "default_value" => $defaultValue,
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
                            ]
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
            } else if ($freeField->getTypage() !== FreeField::TYPE_LIST_MULTIPLE) {
                if(!$edit) {
                    $defaultValue = $freeField->getDefaultValue();
                } else {
                    $inputType = $freeField->getTypage() === FreeField::TYPE_NUMBER ? "number" : "text";
                    $defaultValue = "<input type='$inputType' name='defaultValue' class='$class' value='{$freeField->getDefaultValue()}'/>";
                }
            }

            if($edit) {
                $displayedCreate = $freeField->getDisplayedCreate() ? "checked" : "";
                $requiredCreate = $freeField->getRequiredCreate() ? "checked" : "";
                $requiredEdit = $freeField->getRequiredEdit() ? "checked" : "";
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
                    "requiredCreate" => ($freeField->getRequiredCreate() ? "oui" : "non"),
                    "requiredEdit" => ($freeField->getRequiredEdit() ? "oui" : "non"),
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

    private function canEdit(): bool {
        return $this->userService->hasRightFunction(Menu::PARAM, Action::EDIT);
    }

    private function canDelete(): bool {
        return $this->userService->hasRightFunction(Menu::PARAM, Action::DELETE);
    }

}
