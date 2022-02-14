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

class ActionsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface {

    private ConsoleOutput $output;

    private const SUB_MENU_GENERAL = 'général';
    private const SUB_MENU_TRACKING = 'traçabilité';
    private const SUB_MENU_COLLECTS = 'collectes';
    private const SUB_MENU_DELIVERIES = 'livraisons';
    private const SUB_MENU_HANDLINGS = 'services';
    private const SUB_MENU_DISPATCHES = 'acheminements';
    private const SUB_MENU_TRANSFERS = 'transferts';
    private const SUB_MENU_PURCHASE_REQUESTS = 'achats';
    private const SUB_MENU_PREPARATIONS = 'préparations';
    private const SUB_MENU_RECEPTIONS = 'réceptions';
    private const SUB_MENU_REFERENCES = 'références';
    private const SUB_MENU_ARTICLES = 'articles';
    private const SUB_MENU_SUPPLIER_ARTICLES = 'articles fournisseur';
    private const SUB_MENU_STOCK_MOVEMENTS = 'mouvements de stock';
    private const SUB_MENU_ALERTS = 'alertes';
    private const SUB_MENU_INVENTORY = 'inventaire';

    public const SUB_MENUS = [
        self::SUB_MENU_GENERAL,
        self::SUB_MENU_COLLECTS,
        self::SUB_MENU_DELIVERIES,
        self::SUB_MENU_HANDLINGS,
        self::SUB_MENU_DISPATCHES,
        self::SUB_MENU_TRANSFERS,
        self::SUB_MENU_PURCHASE_REQUESTS,
        self::SUB_MENU_TRACKING,
        self::SUB_MENU_PREPARATIONS,
        self::SUB_MENU_RECEPTIONS,
        self::SUB_MENU_REFERENCES,
        self::SUB_MENU_ARTICLES,
        self::SUB_MENU_SUPPLIER_ARTICLES,
        self::SUB_MENU_STOCK_MOVEMENTS,
        self::SUB_MENU_ALERTS,
        self::SUB_MENU_INVENTORY,
    ];

    public const MENUS = [
        Menu::TRACA => [
            Action::DISPLAY_ARRI,
            Action::DISPLAY_MOUV,
            Action::DISPLAY_ASSO,
            Action::DISPLAY_ENCO,
            Action::DISPLAY_URGE,
            Action::DISPLAY_PACK,
            Action::CREATE,
            Action::EDIT,
            Action::DELETE,
            Action::EXPORT,
            Action::LIST_ALL,
            Action::ADD_PACK,
            Action::EDIT_PACK,
            Action::DELETE_PACK,
            Action::EDIT_ARRI,
            Action::DELETE_ARRI,
            Action::EMPTY_ROUND,
            Action::CREATE_ARRIVAL,
            Action::CREATE_EMERGENCY,
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
            ],
            self::SUB_MENU_TRANSFERS => [
                Action::DISPLAY_ORDRE_TRANS,
            ],
            self::SUB_MENU_RECEPTIONS => [
                Action::DISPLAY_RECE,
                Action::CREATE_REF_FROM_RECEP,
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
            Action::DISPLAY_FOUR,
            Action::DISPLAY_EMPL,
            Action::DISPLAY_CHAU,
            Action::DISPLAY_TRAN,
            Action::CREATE,
            Action::EDIT,
            Action::DELETE,
            Action::EXPORT
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
            Action::DISPLAY_GLOB,
            Action::DISPLAY_ROLE,
            Action::DISPLAY_UTIL,
            Action::DISPLAY_VISIBILITY_GROUPS,
            Action::DISPLAY_DASHBOARDS,
            Action::DISPLAY_EXPO,
            Action::DISPLAY_TYPE,
            Action::DISPLAY_INVE,
            Action::DISPLAY_STATU_LITI,
            Action::DISPLAY_NATU_COLI,
            Action::DISPLAY_CF,
            Action::DISPLAY_REQUEST_TEMPLATE,
            Action::DISPLAY_NOTIFICATIONS,
            Action::DISPLAY_IMPORT,
            Action::EDIT,
            Action::DELETE,
        ],
        Menu::NOMADE => [
            self::SUB_MENU_GENERAL => [
                Action::MODULE_ACCESS_STOCK,
                Action::MODULE_ACCESS_TRACA,
                Action::MODULE_ACCESS_HAND,
                Action::MODULE_NOTIFICATIONS,
                Action::DEMO_MODE,
            ],
            self::SUB_MENU_TRACKING => [
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

        $selectedByDefault = [
            Menu::QUALI => [
                Action::TREAT_DISPUTE,
            ],
            Menu::NOMADE => [
                Action::MODULE_ACCESS_STOCK,
                Action::MODULE_ACCESS_TRACA,
                Menu::NOMADE => Action::MODULE_ACCESS_HAND,
            ],
        ];

        $actionRepository = $manager->getRepository(Action::class);

        $this->deleteUnusedActionsAndMenus($actionRepository, $manager);
        $manager->flush();
        $this->createNewActions(self::MENUS, $actionRepository, $manager, $subActions, $selectedByDefault);
        $manager->flush();
    }

    public function createNewActions(array            $menus,
                                     ActionRepository $actionRepository,
                                     ObjectManager    $manager,
                                     array            $subActions,
                                     array            $selectedByDefault) {

        $roles = $manager->getRepository(Role::class)->findBy([]);
        $subMenuRepository = $manager->getRepository(SubMenu::class);

        foreach($menus as $menuCode => $actionLabels) {
            $counter = 0;
            foreach($actionLabels as $index => $actionLabel) {
                if(is_array($actionLabel)) { // has sub menu
                    $subMenu = $subMenuRepository->findByLabel($menuCode, $index);
                    if (empty($subMenu)) {
                        $subMenu = new SubMenu();
                        $subMenu
                            ->setLabel($index)
                            ->setMenu($this->getReference("menu-$menuCode"));
                        $manager->persist($subMenu);
                    }

                    foreach($actionLabel as $value) {
                        $action = $actionRepository->findOneByParams($menuCode, $value);

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

                        if(array_key_exists($menuCode, $selectedByDefault) && in_array($value, $selectedByDefault[$menuCode])) {
                            foreach($roles as $role) {
                                $action->addRole($role);
                            }
                        }
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

                        // actions à sélectionner par défaut
                        if(array_key_exists($menuCode, $selectedByDefault) && in_array($actionLabel, $selectedByDefault[$menuCode])) {
                            foreach($roles as $role) {
                                $action->addRole($role);
                            }
                        }
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
                    ->keymap(fn($rightOrSubmenu, ?string $menu) => [$menu, is_array($rightOrSubmenu)
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

    public function getDependencies() {
        return [MenusFixtures::class];
    }

    public static function getGroups(): array {
        return ["fixtures"];
    }

}
