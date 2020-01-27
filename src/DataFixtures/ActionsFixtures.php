<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Repository\ActionRepository;
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

    public function __construct(ActionRepository $actionRepository,
                                UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService)
    {
        $this->encoder = $encoder;
        $this->actionRepository = $actionRepository;
        $this->specificService = $specificService;
    }

    public function load(ObjectManager $manager)
    {
    	$menus = [
			Menu::LITIGE => [Action::LIST, Action::CREATE, Action::EDIT, Action::DELETE],
			Menu::RECEPTION => [Action::LIST, Action::CREATE_EDIT, Action::DELETE, Action::CREATE_REF_FROM_RECEP, Action::EXPORT],
			Menu::DEM_LIVRAISON => [Action::LIST, Action::CREATE_EDIT, Action::DELETE, Action::EXPORT],
			Menu::DEM_COLLECTE => [Action::LIST, Action::CREATE_EDIT, Action::DELETE],
			Menu::STOCK => [Action::LIST, Action::CREATE_EDIT, Action::DELETE, Action::EXPORT],
			Menu::REFERENTIEL => [Action::LIST, Action::CREATE_EDIT, Action::DELETE],
			Menu::MANUT => [Action::LIST, Action::CREATE, Action::EDIT_DELETE, Action::EXPORT],
			Menu::PREPA => [Action::LIST, Action::CREATE_EDIT, Action::EXPORT],
			Menu::LIVRAISON => [Action::LIST, Action::CREATE_EDIT, Action::EXPORT],
			Menu::COLLECTE => [Action::LIST, Action::CREATE_EDIT, Action::EXPORT],
			Menu::PARAM => [Action::YES],
			Menu::INVENTAIRE => [Action::LIST, Action::INVENTORY_MANAGER],
			Menu::INDICS_ACCUEIL => [Action::REFERENCE, Action::MONETAIRE],
			Menu::ARRIVAGE => [Action::LIST, Action::CREATE_EDIT, Action::DELETE, Action::LIST_ALL, Action::EXPORT],
		];

    	// spécifique safran
    	if ($this->specificService::isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_CS)) {
    		$menus[Menu::LITIGE][] = Action::TREAT_LITIGE;
		}

		foreach ($menus as $menuCode => $actionLabels) {
			foreach ($actionLabels as $actionLabel) {
				$action = $this->actionRepository->findOneByMenuCodeAndLabel($menuCode, $actionLabel);

				if (empty($action)) {
					$action = new Action();

					$action
						->setLabel($actionLabel)
						->setMenu($this->getReference('menu-' . $menuCode));
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
        return ['actions', 'fixtures'];
    }
}
