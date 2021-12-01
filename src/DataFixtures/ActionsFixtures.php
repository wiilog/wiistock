<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use App\Repository\ActionRepository;
use App\Repository\RoleRepository;
use App\Service\RoleService;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ActionsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;
    private $specificService;
    private $output;
    private $roleService;

    public function __construct(UserPasswordEncoderInterface $encoder,
                                RoleService $roleService,
                                SpecificService $specificService)
    {
        $this->roleService = $roleService;
        $this->encoder = $encoder;
        $this->specificService = $specificService;
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager)
    {
    	$menus = [

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
                Action::EMPTY_ROUND,
			],
			Menu::QUALI => [
				Action::DISPLAY_LITI,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::TREAT_DISPUTE
			],
			Menu::DEM => [
				Action::DISPLAY_TRANSFER_REQ,
				Action::DISPLAY_DEM_COLL,
				Action::DISPLAY_DEM_LIVR,
				Action::DISPLAY_HAND,
                Action::DISPLAY_ACHE,
                Action::DISPLAY_PURCHASE_REQUESTS,
                Action::GENERATE_OVERCONSUMPTION_BILL,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT,
                Action::CREATE_ACHE,
                Action::SHOW_CARRIER_FIELD,
                Action::GENERATE_DELIVERY_NOTE,
                Action::GENERATE_DISPATCH_BILL,
                Action::GENERATE_WAY_BILL,
                Action::TREAT_HANDLING,
                Action::CREATE_PURCHASE_REQUESTS,
                Action::TRACK_SENSOR,
			],
			Menu::ORDRE => [
                Action::DISPLAY_ORDRE_COLL,
                Action::DISPLAY_ORDRE_LIVR,
                Action::DISPLAY_ORDRE_TRANS,
				Action::DISPLAY_PREPA,
				Action::DISPLAY_RECE,
				Action::CREATE_REF_FROM_RECEP,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT,
				Action::TRACK_SENSOR,
				Action::PAIR_SENSOR,
			],
			Menu::STOCK => [
				Action::DISPLAY_ARTI,
				Action::DISPLAY_REFE,
				Action::DISPLAY_ARTI_FOUR,
				Action::DISPLAY_MOUV_STOC,
				Action::DISPLAY_INVE,
				Action::DISPLAY_ALER,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT,
				Action::INVENTORY_MANAGER,
                Action::EXPORT_ALER
			],
			Menu::REFERENTIEL => [
				Action::DISPLAY_FOUR,
				Action::DISPLAY_EMPL,
				Action::DISPLAY_CHAU,
				Action::DISPLAY_TRAN,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE
			],
			Menu::IOT => [
				Action::DISPLAY_SENSOR,
				Action::DISPLAY_TRIGGER,
				Action::DISPLAY_PAIRING,
                Action::CREATE,
                Action::EDIT,
                Action::DELETE
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
                Action::MODULE_ACCESS_STOCK,
                Action::MODULE_ACCESS_TRACA,
                Action::MODULE_ACCESS_GROUP,
                Action::MODULE_ACCESS_UNGROUP,
                Action::MODULE_ACCESS_HAND,
                Action::MODULE_NOTIFICATIONS,
                Action::DEMO_MODE
            ]
		];

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
                ]
            ]
        ];

        $selectedByDefault = [
            Menu::QUALI => [
                Action::TREAT_DISPUTE,
            ],
            Menu::NOMADE => [
                Action::MODULE_ACCESS_STOCK,
                Action::MODULE_ACCESS_TRACA,
                Menu::NOMADE => Action::MODULE_ACCESS_HAND
            ]
        ];
        $actionRepository = $manager->getRepository(Action::class);
        $roleRepository = $manager->getRepository(Role::class);

        $this->deleteUnusedActionsAndMenus($actionRepository, $menus, $manager, $subActions);
        $manager->flush();
        $this->createNewActions($menus, $actionRepository, $manager, $roleRepository, $subActions, $selectedByDefault);
		$manager->flush();
    }

    public function createNewActions(array $menus,
                                     ActionRepository $actionRepository,
                                     ObjectManager $manager,
                                     RoleRepository $roleRepository,
                                     array $subActions,
                                     array $selectedByDefault) {
        foreach ($menus as $menuCode => $actionLabels) {
            foreach ($actionLabels as $actionLabel) {
                $hasSubActions = array_key_exists($menuCode, $subActions) && array_key_exists($actionLabel, $subActions[$menuCode]);
                $action = $actionRepository->findOneByMenuLabelAndActionLabel($menuCode, $actionLabel);

                if (empty($action)) {
                    $action = new Action();

                    $action
                        ->setLabel($actionLabel)
                        ->setMenu($this->getReference('menu-' . $menuCode));

                    // actions à sélectionner par défaut
                    if (array_key_exists($menuCode, $selectedByDefault) && in_array($actionLabel, $selectedByDefault[$menuCode])) {
                        if (!isset($roles)) {
                            $roles = $roleRepository->findAll();
                        }
                        foreach ($roles as $role) {
                            $action->addRole($role);
                        }
                    }
                    $manager->persist($action);
                    $this->output->writeln("création de l'action " . $menuCode . " / " . $actionLabel);
                }
                $manager->flush();
                if (!$action->getRoles()->isEmpty() && $hasSubActions) {
                    foreach ($subActions[$menuCode][$actionLabel] as $subActionLabel) {
                        $subAction = $actionRepository->findOneByMenuLabelAndActionLabel($menuCode, $subActionLabel);
                        if (empty($subAction)) {
                            $subAction = new Action();

                            $subAction
                                ->setLabel($subActionLabel)
                                ->setMenu($this->getReference('menu-' . $menuCode));

                            foreach ($action->getRoles()->toArray() as $role) {
                                $subAction->addRole($role);
                            }
                            $manager->persist($subAction);
                            $this->output->writeln("création de l'action " . $menuCode . " / " . $subActionLabel);
                        }
                    }
                }
            }
        }
    }

    public function deleteUnusedActionsAndMenus(ActionRepository $actionRepository, array $menus, ObjectManager $manager, array $subActions) {
        $allSavedActions = $actionRepository->findAll();

        $menusToLower = array_reduce(array_keys($menus), function (array $carry, string $menuLabel) use ($menus, $subActions) {

            $carry[mb_strtolower($menuLabel)] = array_map(function ($action) {
                return mb_strtolower($action);
            }, $menus[mb_strtolower($menuLabel)]);

            if (array_key_exists(mb_strtolower($menuLabel), $subActions)) {
                foreach ($subActions[mb_strtolower($menuLabel)] as $parentActions) {
                    foreach ($parentActions as $subAction) {
                        $carry[mb_strtolower($menuLabel)][] = $subAction;
                    }
                }
            }

            return $carry;
        }, []);

        foreach ($allSavedActions as $savedAction) {
            $menu = $savedAction->getMenu();
            $menuLabelToLower = mb_strtolower($menu->getLabel());
            $actionLabelToLower = mb_strtolower($savedAction->getLabel());
            if (!isset($menusToLower[$menuLabelToLower])) {
                foreach ($menu->getActions()->filter(function (Action $action) {
                    return !$action->getDashboard();
                }) as $action) {
                    $manager->remove($action);
                    $this->output->writeln("Suppression du droit :  $menuLabelToLower / $actionLabelToLower");
                }
                if (!$savedAction->getDashboard()) {
                    $manager->remove($menu);
                }
            } else if (!in_array($actionLabelToLower, $menusToLower[$menuLabelToLower])) {
                $manager->remove($savedAction);
                $this->output->writeln("Suppression du droit :  $menuLabelToLower / $actionLabelToLower");
            }
        }
    }

    public function getDependencies()
    {
        return [MenusFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['fixtures', 'patch-menus'];
    }
}
