<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ActionsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;
    private $specificService;

    public function __construct(UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService)
    {
        $this->encoder = $encoder;
        $this->specificService = $specificService;
    }

    public function load(ObjectManager $manager)
    {
    	$menus = [
    		Menu::ACCUEIL => [
    			Action::DISPLAY_INDI,
    			Action::DISPLAY_INDIC_INV_MONETAIRE,
				Action::DISPLAY_INDIC_INV_REFERENCE
			],
			Menu::TRACA => [
				Action::DISPLAY_ARRI,
				Action::DISPLAY_MOUV,
				Action::DISPLAY_ASSO,
				Action::DISPLAY_ENCO,
				Action::DISPLAY_URGE,
				Action::DISPLAY_PACK,
				Action::ADD_PACK,
				Action::EDIT_PACK,
				Action::DELETE_PACK,
				Action::EDIT_ARRI,
				Action::DELETE_ARRI,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT,
				Action::LIST_ALL
			],
			Menu::QUALI => [
				Action::DISPLAY_LITI,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::TREAT_LITIGE
			],
			Menu::DEM => [
				Action::DISPLAY_DEM_COLL,
				Action::DISPLAY_DEM_LIVR,
				Action::DISPLAY_HAND,
                Action::DISPLAY_ACHE,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT,
                Action::CREATE_ACHE,
                Action::DELETE_ACHE,
                Action::EDIT_UNPROCESSED_DISPATCH,
                Action::DELETE_UNPROCESSED_DISPATCH,
                Action::SHOW_CARRIER_FIELD
			],
			Menu::ORDRE => [
				Action::DISPLAY_ORDRE_COLL,
				Action::DISPLAY_ORDRE_LIVR,
				Action::DISPLAY_PREPA,
				Action::DISPLAY_RECE,
				Action::CREATE_REF_FROM_RECEP,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT
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
				Action::INVENTORY_MANAGER
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
			Menu::PARAM => [
				Action::DISPLAY_GLOB,
				Action::DISPLAY_ROLE,
				Action::DISPLAY_UTIL,
				Action::DISPLAY_CL,
				Action::DISPLAY_EXPO,
				Action::DISPLAY_TYPE,
				Action::DISPLAY_INVE,
				Action::DISPLAY_STATU_LITI,
				Action::DISPLAY_NATU_COLI,
				Action::DISPLAY_CF,
				Action::EDIT,
				Action::DELETE,
				Action::DISPLAY_IMPORT
			],
            Menu::NOMADE => [
                Action::MODULE_ACCESS_STOCK,
                Action::MODULE_ACCESS_TRACA,
                Action::MODULE_ACCESS_HAND,
                Action::DEMO_MODE
            ]
		];

        $specifics = [
            Action::SHOW_CARRIER_FIELD => [
                SpecificService::CLIENT_EMERSON
            ]
        ];

    	$selectedByDefault = [
    		Menu::QUALI . Action::TREAT_LITIGE,
    		Menu::DEM . Action::SHOW_CARRIER_FIELD,
    		Menu::NOMADE . Action::MODULE_ACCESS_STOCK,
    		Menu::NOMADE . Action::MODULE_ACCESS_TRACA,
    		Menu::NOMADE . Action::MODULE_ACCESS_HAND
		];
        $actionRepository = $manager->getRepository(Action::class);
        $roleRepository = $manager->getRepository(Role::class);
		foreach ($menus as $menuCode => $actionLabels) {
			foreach ($actionLabels as $actionLabel) {
				$action = $actionRepository->findOneByMenuLabelAndActionLabel($menuCode, $actionLabel);

                $canCreate = (
                    !isset($specifics[$actionLabel]) ||
                    in_array($this->specificService->getAppClient(), $specifics[$actionLabel])
                );

				if (empty($action) && $canCreate) {
					$action = new Action();

					$action
						->setLabel($actionLabel)
						->setMenu($this->getReference('menu-' . $menuCode));

					// actions à sélectionner par défaut
					if (in_array($menuCode . $actionLabel, $selectedByDefault)) {
					    if (!isset($roles)) {
                            $roles = $roleRepository->findAll();
                        }
						foreach ($roles as $role) {
							$action->addRole($role);
						}
					}
					$manager->persist($action);
					dump("création de l'action " . $menuCode . " / " . $actionLabel);
				}
			}
		}

		$manager->flush();
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
