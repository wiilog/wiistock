<?php

namespace App\Controller;

use App\Entity\DaysWorked;
use App\Entity\MailerServer;
use App\Entity\ParametrageGlobal;
use App\Entity\WorkFreeDay;
use App\Helper\FormatHelper;
use App\Service\AttachmentService;
use App\Service\SettingsService;
use App\Service\SpecificService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\HasPermission;
use App\Entity\Menu;
use App\Entity\Action;

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
            "menus" => [
                self::MENU_DISPATCHES => "Acheminements",
                self::MENU_PREPARATIONS => "Préparations",
                self::MENU_HANDLINGS => "Services",
                self::MENU_VALIDATION => "Gestion des validations",
                self::MENU_TRANSFERS => "Transfert à traiter",
            ],
        ],
        self::CATEGORY_DASHBOARDS => [
            "label" => "Dashboards",
            "icon" => "accueil",
            "menus" => [
                self::MENU_FULL_SETTINGS => "Paramétrage complet",
            ],
        ],
        self::CATEGORY_IOT => [
            "label" => "IoT",
            "icon" => "accueil",
            "menus" => [
                self::MENU_TYPES_FREE_FIELDS => "Types et champs libres",
            ],
        ],
        self::CATEGORY_NOTIFICATIONS => [
            "label" => "Modèles de notifications",
            "icon" => "accueil",
            "menus" => [
                self::MENU_ALERTS => "Alertes",
                self::MENU_PUSH_NOTIFICATIONS => "Notifications push",
            ],
        ],
        self::CATEGORY_USERS => [
            "label" => "Utilisateurs",
            "icon" => "accueil",
            "menus" => [
                self::MENU_LANGUAGES => "Langues",
                self::MENU_USERS => "Utilisateurs",
                self::MENU_ROLES => "Rôles",
            ],
        ],
        self::CATEGORY_DATA => [
            "label" => "Données",
            "icon" => "accueil",
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
     * @Route("/", name="settings_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB})
     */
    public function index(): Response {
        return $this->render("settings/list.html.twig", [
            "settings" => self::SETTINGS,
        ]);
    }

    /**
     * @Route("/{category}/{menu}/{submenu}", name="settings_item")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB})
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
            "values" => $this->values(),
        ]);
    }

    public function values(): array {
        $globalSettingsRepository = $this->manager->getRepository(ParametrageGlobal::class);
        $mailerServerRepository = $this->manager->getRepository(MailerServer::class);

        return [
            self::CATEGORY_GLOBAL => [
                self::MENU_SITE_APPEARANCE => [],
                self::MENU_CLIENT => [
                    "current_client" => $this->specificService->getAppClient(),
                ],
                self::MENU_MAIL_SERVER => [
                    "mailer_server" => $mailerServerRepository->findOneBy([]),
                ],
                self::MENU_LABELS => [],
            ],
            self::CATEGORY_STOCK => [
                self::MENU_ALERTS => [
                    "alertThreshold" => $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_WARNING_THRESHOLD),
                    "securityThreshold" => $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_SECURITY_THRESHOLD),
                    "expirationDelay" => $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY),
                ],
            ],
        ];
    }

    /**
     * @Route("/enregistrer", name="settings_save", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB})
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
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB})
     */
    public function workingHoursApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $daysWorkedRepository = $manager->getRepository(DaysWorked::class);

        $data = [];
        foreach($daysWorkedRepository->findAll() as $day) {
            if($edit) {
                $worked = $day->getWorked() ? "checked" : "";
                $data[] = [
                    "day" => $day->getDisplayDay(),
                    "hours" => "<input name='hours' class='$class' data-global-error='Quantité' value='{$day->getTimes()}'/>",
                    "worked" => "<div class='checkbox-container'><input type='checkbox' name='worked' class='$class' $worked/></div>",
                ];
            } else {
                $data[] = [
                    "day" => $day->getDisplayDay(),
                    "hours" => $day->getTimes(),
                    "worked" => $day->getWorked() ? "Oui" : "Non",
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
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB})
     */
    public function offDaysApi(Request $request, EntityManagerInterface $manager) {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);
        $class = "form-control data";

        $workFreeDayRepository = $manager->getRepository(WorkFreeDay::class);

        $data = [];
        foreach($workFreeDayRepository->findAll() as $day) {
            if($edit) {
                $data[] = [
                    "actions" => "
                    <button class='btn btn-silent delete-row' data-id='{{ dispatchPack ? dispatchPack.id }}'>
                        <i class='wii-icon wii-icon-trash text-primary'></i>
                    </button>
                    ",
                    "day" => "<input type='date' name='day' class='$class' data-global-error='Jour' value='{$day->getDay()->format('Y-m-d')}' required/>",
                ];
            } else {
                $data[] = [
                    "actions" => "",
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

}
