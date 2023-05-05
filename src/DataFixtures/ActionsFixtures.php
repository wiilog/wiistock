<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\SubMenu;
use App\Repository\ActionRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use WiiCommon\Helper\Stream;

class ActionsFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface {

    private ConsoleOutput $output;

    const SUB_MENU_GENERAL = 'général';
    const SUB_MENU_GLOBAL = 'global';
    const SUB_MENU_STOCK = 'stock';
    const SUB_MENU_TERMINAL_MOBILE = 'terminal mobile';
    const SUB_MENU_DASHBOARD = 'dashboard';
    const SUB_MENU_IOT = 'iot';
    const SUB_MENU_NOTIFICATIONS = 'modèles de notifications';
    const SUB_MENU_USERS = 'utilisateurs';
    const SUB_MENU_DATA = 'données';
    const SUB_MENU_TEMPLATES = 'modèles';
    const SUB_MENU_PAGE = 'page';
    const SUB_MENU_TRACKING = 'track';
    const SUB_MENU_TRACING = 'trace';
    const SUB_MENU_COLLECTS = 'collectes';
    const SUB_MENU_DELIVERIES = 'livraisons';
    const SUB_MENU_HANDLINGS = 'services';
    const SUB_MENU_DISPATCHES = 'acheminements';
    const SUB_MENU_TRANSFERS = 'transferts';
    const SUB_MENU_PURCHASE_REQUESTS = 'achats';
    const SUB_MENU_TRANSPORT = 'transport';
    const SUB_MENU_TRANSPORT_PLANNING = 'planning';
    const SUB_MENU_TRANSPORT_ROUND = 'tournée';
    const SUB_MENU_TRANSPORT_SUBCONTRACT = 'sous-traitance';
    const SUB_MENU_PREPARATIONS = 'préparations';
    const SUB_MENU_RECEPTIONS = 'réceptions';
    const SUB_MENU_REFERENCES = 'références';
    const SUB_MENU_ARTICLES = 'articles';
    const SUB_MENU_SUPPLIER_ARTICLES = 'articles fournisseur';
    const SUB_MENU_STOCK_MOVEMENTS = 'mouvements de stock';
    const SUB_MENU_ALERTS = 'alertes';
    const SUB_MENU_INVENTORY = 'inventaire';
    const SUB_MENU_ARRIVALS = 'arrivages UL';
    const SUB_MENU_TRUCK_ARRIVALS = 'arrivages camion';
    const SUB_MENU_MOVEMENTS = 'mouvements';
    const SUB_MENU_PACKS = 'colis';
    const SUB_MENU_ASSOCIATION_BR = 'association BR';
    const SUB_MENU_ENCO = 'encours';
    const SUB_MENU_EMERGENCYS = 'urgences';

    public const MENUS = [
        Menu::TRACA => [
            self::SUB_MENU_GENERAL => [
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::EXPORT,
            ],
            self::SUB_MENU_ARRIVALS => [
                Action::DISPLAY_ARRI,
                Action::LIST_ALL,
                Action::CREATE_ARRIVAL,
                Action::EDIT_ARRI,
                Action::DELETE_ARRI,
                Action::ADD_PACK,
                Action::EDIT_PACK,
                Action::DELETE_PACK,
            ],
            self::SUB_MENU_TRUCK_ARRIVALS => [
                Action::DISPLAY_TRUCK_ARRIVALS,
                Action::CREATE_TRUCK_ARRIVALS,
                Action::EDIT_TRUCK_ARRIVALS,
                Action::DELETE_TRUCK_ARRIVALS,
                Action::ADD_CARRIER_TRACKING_NUMBER,
                Action::DELETE_CARRIER_TRACKING_NUMBER,
                Action::EDIT_RESERVES,
            ],
            self::SUB_MENU_MOVEMENTS => [
                Action::DISPLAY_MOUV,
                Action::CREATE_TRACKING_MOVEMENT,
                Action::FULLY_EDIT_TRACKING_MOVEMENTS,
                Action::EMPTY_ROUND,
            ],
            self::SUB_MENU_PACKS => [
                Action::DISPLAY_PACK,
            ],
            self::SUB_MENU_ASSOCIATION_BR => [
                Action::DISPLAY_ASSO,
            ],
            self::SUB_MENU_ENCO => [
                Action::DISPLAY_ENCO,
            ],
            self::SUB_MENU_EMERGENCYS => [
                Action::DISPLAY_URGE,
                Action::CREATE_EMERGENCY,
            ],
        ],
        Menu::QUALI => [
            Action::DISPLAY_LITI,
            Action::CREATE,
            Action::EDIT,
            Action::DELETE,
            Action::TREAT_DISPUTE,
        ],
        Menu::DEM => [
            self::SUB_MENU_GENERAL => [
                Action::EXPORT,
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
            ],
            self::SUB_MENU_COLLECTS => [
                Action::DISPLAY_DEM_COLL,
            ],
            self::SUB_MENU_DELIVERIES => [
                Action::DISPLAY_DEM_LIVR,
                Action::TRACK_SENSOR,
            ],
            self::SUB_MENU_HANDLINGS => [
                Action::DISPLAY_HAND,
                Action::TREAT_HANDLING,
                Action::DELETE_UNPROCESSED_HANDLING,
                Action::DELETE_PROCESSED_HANDLING,
            ],
            self::SUB_MENU_DISPATCHES => [
                Action::DISPLAY_ACHE,
                Action::CREATE_ACHE,
                Action::EDIT_DRAFT_DISPATCH,
                Action::EDIT_UNPROCESSED_DISPATCH,
                Action::EDIT_PROCESSED_DISPATCH,
                Action::DELETE_DRAFT_DISPATCH,
                Action::DELETE_UNPROCESSED_DISPATCH,
                Action::DELETE_PROCESSED_DISPATCH,
                Action::GROUPED_SIGNATURE,
                Action::ADD_REFERENCE_IN_LU,
                Action::MANAGE_PACK,
                Action::SHOW_CARRIER_FIELD,
                Action::GENERATE_DISPATCH_BILL,
                Action::GENERATE_DELIVERY_NOTE,
                Action::GENERATE_OVERCONSUMPTION_BILL,
                Action::GENERATE_WAY_BILL,
            ],
            self::SUB_MENU_TRANSFERS => [
                Action::DISPLAY_TRANSFER_REQ,
            ],
            self::SUB_MENU_PURCHASE_REQUESTS => [
                Action::DISPLAY_PURCHASE_REQUESTS,
                Action::CREATE_PURCHASE_REQUESTS,
                Action::EDIT_DRAFT_PURCHASE_REQUEST,
                Action::EDIT_ONGOING_PURCHASE_REQUESTS,
                Action::DELETE_DRAFT_PURCHASE_REQUEST,
                Action::DELETE_ONGOING_PURCHASE_REQUESTS,
                Action::DELETE_TREATED_PURCHASE_REQUESTS,
            ],
            self::SUB_MENU_TRANSPORT => [
                Action::DISPLAY_TRANSPORT,
                Action::CREATE_TRANSPORT,
                Action::EDIT_TRANSPORT,
                Action::DELETE_TRANSPORT,
            ],
        ],
        Menu::ORDRE => [
            self::SUB_MENU_GENERAL => [
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::EXPORT,
            ],
            self::SUB_MENU_COLLECTS => [
                Action::DISPLAY_ORDRE_COLL,
            ],
            self::SUB_MENU_DELIVERIES => [
                Action::DISPLAY_ORDRE_LIVR,
                Action::TRACK_SENSOR,
                Action::PAIR_SENSOR,
            ],
            self::SUB_MENU_PREPARATIONS => [
                Action::DISPLAY_PREPA,
                Action::DISPLAY_PREPA_PLANNING,
                Action::EDIT_PREPARATION_DATE,
            ],
            self::SUB_MENU_TRANSFERS => [
                Action::DISPLAY_ORDRE_TRANS,
            ],
            self::SUB_MENU_RECEPTIONS => [
                Action::DISPLAY_RECE,
                Action::CREATE_REF_FROM_RECEP,
            ],
            self::SUB_MENU_TRANSPORT => [
                Action::DISPLAY_TRANSPORT,
                Action::EDIT_TRANSPORT,
            ],
            self::SUB_MENU_TRANSPORT_PLANNING => [
                Action::DISPLAY_TRANSPORT_PLANNING,
                Action::SCHEDULE_TRANSPORT_ROUND,
            ],
            self::SUB_MENU_TRANSPORT_ROUND => [
                Action::DISPLAY_TRANSPORT_ROUND,
                Action::EDIT_TRANSPORT_ROUND,
            ],
            self::SUB_MENU_TRANSPORT_SUBCONTRACT => [
                Action::DISPLAY_TRANSPORT_SUBCONTRACT,
                Action::EDIT_TRANSPORT_SUBCONTRACT,
            ],
        ],
        Menu::STOCK => [
            self::SUB_MENU_GENERAL => [
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::EXPORT,
            ],
            self::SUB_MENU_REFERENCES => [
                Action::DISPLAY_REFE,
                Action::CREATE_DRAFT_REFERENCE,
                Action::EDIT_PARTIALLY,
                Action::REFERENCE_VALIDATOR,
            ],
            self::SUB_MENU_ARTICLES => [
                Action::DISPLAY_ARTI,
            ],
            self::SUB_MENU_SUPPLIER_ARTICLES => [
                Action::DISPLAY_ARTI_FOUR,
            ],
            self::SUB_MENU_STOCK_MOVEMENTS => [
                Action::DISPLAY_MOUV_STOC,
            ],
            self::SUB_MENU_ALERTS => [
                Action::DISPLAY_ALER,
                Action::EXPORT_ALER,
            ],
            self::SUB_MENU_INVENTORY => [
                Action::DISPLAY_INVE,
                Action::INVENTORY_MANAGER,
            ],
        ],
        Menu::REFERENTIEL => [
            self::SUB_MENU_GENERAL => [
                Action::CREATE,
                Action::EDIT,
                Action::DELETE,
                Action::EXPORT,
            ],
            self::SUB_MENU_PAGE => [
                Action::DISPLAY_FOUR,
                Action::DISPLAY_EMPL,
                Action::DISPLAY_CHAU,
                Action::DISPLAY_TRAN,
                Action::DISPLAY_VEHICLE,
                Action::DISPLAY_PACK_NATURE,
            ],
        ],
        Menu::IOT => [
            Action::DISPLAY_SENSOR,
            Action::DISPLAY_TRIGGER,
            Action::DISPLAY_PAIRING,
            Action::CREATE,
            Action::EDIT,
            Action::DELETE,
        ],
        Menu::PARAM => [
            self::SUB_MENU_GENERAL => [
                Action::EDIT,
                Action::DELETE,
            ],
            self::SUB_MENU_GLOBAL => [
                Action::SETTINGS_DISPLAY_WEBSITE_APPEARANCE,
                Action::SETTINGS_DISPLAY_APPLICATION_CLIENT,
                Action::SETTINGS_DISPLAY_BILL,
                Action::SETTINGS_DISPLAY_WORKING_HOURS,
                Action::SETTINGS_DISPLAY_NOT_WORKING_DAYS,
                Action::SETTINGS_DISPLAY_MAIL_SERVER,
            ],
            self::SUB_MENU_STOCK => [
                Action::SETTINGS_DISPLAY_CONFIGURATIONS,
                Action::SETTINGS_DISPLAY_STOCK_ALERTS,
                Action::SETTINGS_DISPLAY_ARTICLES,
                Action::SETTINGS_DISPLAY_TOUCH_TERMINAL,
                Action::SETTINGS_DISPLAY_REQUESTS,
                Action::SETTINGS_DISPLAY_VISIBILITY_GROUPS,
                Action::SETTINGS_DISPLAY_INVENTORIES,
                Action::SETTINGS_DISPLAY_RECEP
            ],
            self::SUB_MENU_TRACING => [
                Action::SETTINGS_DISPLAY_TRACING_DISPATCH,
                Action::SETTINGS_DISPLAY_ARRI,
                Action::SETTINGS_DISPLAY_TRUCK_ARRIVALS,
                Action::SETTINGS_DISPLAY_MOVEMENT,
                Action::SETTINGS_DISPLAY_TRACING_HAND
            ],
            self::SUB_MENU_TRACKING => [
                Action::SETTINGS_DISPLAY_TRACK_REQUESTS,
                Action::SETTINGS_DISPLAY_ROUND,
                Action::SETTINGS_DISPLAY_TEMPERATURES,
            ],
            self::SUB_MENU_TERMINAL_MOBILE => [
                Action::SETTINGS_DISPLAY_MOBILE_DISPATCH,
                Action::SETTINGS_DISPLAY_MOBILE_HAND,
                Action::SETTINGS_DISPLAY_TRANSFER_TO_TREAT,
                Action::SETTINGS_DISPLAY_PREPA,
                Action::SETTINGS_DISPLAY_MANAGE_VALIDATIONS
            ],
            self::SUB_MENU_DASHBOARD => [
                Action::SETTINGS_DISPLAY_DASHBOARD
            ],
            self::SUB_MENU_IOT => [
                Action::SETTINGS_DISPLAY_IOT
            ],
            self::SUB_MENU_NOTIFICATIONS => [
                Action::SETTINGS_DISPLAY_NOTIFICATIONS_ALERTS,
                Action::SETTINGS_DISPLAY_NOTIFICATIONS_PUSH
            ],
            self::SUB_MENU_USERS => [
                Action::SETTINGS_DISPLAY_LABELS_PERSO,
                Action::SETTINGS_DISPLAY_ROLES,
                Action::SETTINGS_DISPLAY_USERS
            ],
            self::SUB_MENU_DATA => [
                Action::SETTINGS_DISPLAY_EXPORT_ENCODING,
                Action::SETTINGS_DISPLAY_EXPORT,
                Action::SETTINGS_DISPLAY_IMPORTS_MAJS,
                Action::SETTINGS_DISPLAY_INVENTORIES_IMPORT
            ],
            self::SUB_MENU_TEMPLATES => [
                Action::SETTINGS_DISPLAY_DISPATCH_TEMPLATE,
                Action::SETTINGS_DISPLAY_DELIVERY_TEMPLATE,
            ],
        ],
        Menu::NOMADE => [
            self::SUB_MENU_GENERAL => [
                Action::ACCESS_NOMADE_LOGIN,
                Action::MODULE_ACCESS_STOCK,
                Action::MODULE_ACCESS_TRACA,
                Action::MODULE_ACCESS_HAND,
                Action::MODULE_NOTIFICATIONS,
                Action::MODULE_TRACK,
                Action::DEMO_MODE,
            ],
            self::SUB_MENU_TRACING => [
                Action::MODULE_ACCESS_TRUCK_ARRIVALS,
                Action::MODULE_ACCESS_GROUP,
                Action::MODULE_ACCESS_UNGROUP,
            ],
        ],
    ];

    public function __construct() {
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager) {
        $subActions = [
            Menu::DEM => [
                Action::DELETE => [
                    Action::DELETE_DRAFT_DISPATCH,
                    Action::DELETE_UNPROCESSED_DISPATCH,
                    Action::DELETE_PROCESSED_DISPATCH,
                    Action::DELETE_UNPROCESSED_HANDLING,
                    Action::DELETE_PROCESSED_HANDLING,
                    Action::DELETE_DRAFT_PURCHASE_REQUEST,
                    Action::DELETE_ONGOING_PURCHASE_REQUESTS,
                    Action::DELETE_TREATED_PURCHASE_REQUESTS,
                ],
                Action::EDIT => [
                    Action::EDIT_DRAFT_DISPATCH,
                    Action::EDIT_UNPROCESSED_DISPATCH,
                    Action::EDIT_PROCESSED_DISPATCH,
                    Action::MANAGE_PACK,
                    Action::EDIT_DRAFT_PURCHASE_REQUEST,
                    Action::EDIT_ONGOING_PURCHASE_REQUESTS,
                ],
            ],
            Menu::TRACA => [
                Action::EDIT => [
                    Action::EDIT_ARRI,
                    Action::ADD_PACK,
                    Action::EDIT_PACK,
                    Action::DELETE_PACK,
                ],
                Action::DELETE => [
                    Action::DELETE_ARRI,
                ],
            ],
        ];

        $actionRepository = $manager->getRepository(Action::class);

        $this->deleteUnusedActionsAndMenus($actionRepository, $manager);
        $manager->flush();
        $this->createNewActions(self::MENUS, $actionRepository, $manager, $subActions);
        $manager->flush();
    }

    public function createNewActions(array            $menus,
                                     ActionRepository $actionRepository,
                                     ObjectManager    $manager,
                                     array            $subActions) {

        $roles = $manager->getRepository(Role::class)->findBy([]);
        $subMenuRepository = $manager->getRepository(SubMenu::class);

        foreach($menus as $menuCode => $actionLabels) {
            $counter = 0;
            foreach($actionLabels as $index => $actionLabel) {
                if(is_array($actionLabel)) { // has sub menu
                    $subMenu = $subMenuRepository->findOneByLabel($menuCode, $index);
                    if (empty($subMenu)) {
                        $subMenu = new SubMenu();
                        $subMenu
                            ->setLabel($index)
                            ->setMenu($this->getReference("menu-$menuCode"));
                        $manager->persist($subMenu);
                    }
                    $manager->flush();

                    foreach($actionLabel as $value) {
                        $action = $actionRepository->findOneByParams($menuCode, $value, $subMenu);

                        if(empty($action)) {
                            $action = new Action();
                            $action
                                ->setLabel($value)
                                ->setMenu($this->getReference("menu-$menuCode"));

                            $manager->persist($action);
                        }

                        $action
                            ->setSubMenu($subMenu)
                            ->setDisplayOrder($counter);

                        $counter++;
                    }
                    $manager->flush();
                } else {
                    $hasSubActions = array_key_exists($menuCode, $subActions) && array_key_exists($index, $subActions[$menuCode]);
                    $action = $actionRepository->findOneByParams($menuCode, $actionLabel);

                    if(empty($action)) {
                        $action = new Action();

                        $action
                            ->setLabel($actionLabel)
                            ->setMenu($this->getReference("menu-$menuCode"));

                        $manager->persist($action);
                        $this->output->writeln("création de l'action " . $menuCode . " / " . $actionLabel);
                    }

                    $action->setDisplayOrder($counter);
                    $counter++;
                    $manager->flush();
                    if(!$action->getRoles()->isEmpty() && $hasSubActions) {
                        foreach($subActions[$menuCode][$actionLabel] as $subActionLabel) {
                            $subAction = $actionRepository->findOneByParams($menuCode, $subActionLabel);
                            if(empty($subAction)) {
                                $subAction = new Action();

                                $subAction
                                    ->setLabel($subActionLabel)
                                    ->setMenu($this->getReference("menu-$menuCode"));

                                foreach($action->getRoles()->toArray() as $role) {
                                    $subAction->addRole($role);
                                }
                                $manager->persist($subAction);
                                $this->output->writeln("création de l'action $menuCode / $subActionLabel");
                            }
                            $subAction->setDisplayOrder($counter);
                            $counter++;
                        }
                    }
                }
            }
        }
    }

    public function deleteUnusedActionsAndMenus(ActionRepository $actionRepository, ObjectManager $manager) {
        $allSavedActions = $actionRepository->findAll();

        $menusToLower = Stream::from(self::MENUS)
            ->keymap(fn(array $rights, string $menu) => [
                mb_strtolower($menu),
                Stream::from($rights)
                    ->keymap(fn($rightOrSubmenu, ?string $menu) => [mb_strtolower($menu), is_array($rightOrSubmenu)
                        ? Stream::from($rightOrSubmenu)
                            ->map(fn(string $right) => mb_strtolower($right))
                            ->values()
                        : mb_strtolower($rightOrSubmenu)])
                    ->toArray(),
            ])
            ->toArray();

        foreach($allSavedActions as $action) {
            $menu = $action->getMenu();
            $subMenu = $action->getSubMenu();
            $menuLabelToLower = mb_strtolower($menu->getLabel());
            $subMenuLabelToLower = $subMenu ? mb_strtolower($subMenu->getLabel()) : null;
            $actionLabelToLower = mb_strtolower($action->getLabel());

            if($action->getDashboard()) {
                continue;
            }

            if($subMenuLabelToLower !== null) {
                if(!isset($menusToLower[$menuLabelToLower][$subMenuLabelToLower])) {
                    $manager->remove($subMenu);
                    $this->output->writeln("Suppression du sous-menu :  $menuLabelToLower / $subMenuLabelToLower");
                }

                $rights = $menusToLower[$menuLabelToLower][$subMenuLabelToLower] ?? [];
            } else {
                if(!isset($menusToLower[$menuLabelToLower])) {
                    $manager->remove($menu);
                    $this->output->writeln("Suppression du menu :  $menuLabelToLower");
                }

                $rights = $menusToLower[$menuLabelToLower] ?? [];
            }


            if(!in_array($actionLabelToLower, $rights)) {
                $manager->remove($action);
                $this->output->writeln("Suppression du droit :  $menuLabelToLower / $subMenuLabelToLower / $actionLabelToLower");
            }
        }
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

    public function getDependencies(): array {
        return [MenusFixtures::class];
    }
}
