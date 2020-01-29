<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Repository\ActionRepository;
use App\Repository\RoleRepository;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ActionsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;
    private $actionRepository;
    private $specificService;
    private $roleRepository;

    public function __construct(ActionRepository $actionRepository,
                                UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService,
								RoleRepository $roleRepository
	)
    {
        $this->encoder = $encoder;
        $this->actionRepository = $actionRepository;
        $this->specificService = $specificService;
        $this->roleRepository = $roleRepository;
    }

    public function load(ObjectManager $manager)
    {
    	$menus = [
    		Menu::ACCUEIL => [
    			Action::DISPLAY_INDI,
    			Action::INDIC_INV_MONETAIRE,
				Action::INDIC_INV_REFERENCE
			],
			Menu::TRACA => [
				Action::DISPLAY_ARRI,
				Action::DISPLAY_MOUV,
				Action::DISPLAY_ACHE,
				Action::DISPLAY_ASSO,
				Action::DISPLAY_ENCO,
				Action::DISPLAY_URGE,
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
				Action::DISPLAY_MANU,
				Action::CREATE,
				Action::EDIT,
				Action::DELETE,
				Action::EXPORT
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
				Action::SEE_REAL_QUANTITY,
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
				Action::DISPLAY_STATU_LITI,
				Action::DISPLAY_NATU_COLI,
				Action::DISPLAY_CF,
				Action::EDIT,
				Action::DELETE,
			]
		];

    	$selectedByDefault = [
    		Menu::QUALI . Action::TREAT_LITIGE
		];

		foreach ($menus as $menuCode => $actionLabels) {
			foreach ($actionLabels as $actionLabel) {
				$action = $this->actionRepository->findOneByMenuLabelAndActionLabel($menuCode, $actionLabel);

				if (empty($action)) {
					$action = new Action();

					$action
						->setLabel($actionLabel)
						->setMenu($this->getReference('menu-' . $menuCode));

					// actions à sélectionner par défaut
					if (in_array($menuCode . $actionLabel, $selectedByDefault)) {
					    if (!isset($roles)) {
                            $roles = $this->roleRepository->findAll();
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
        return ['fixtures'];
    }
}
