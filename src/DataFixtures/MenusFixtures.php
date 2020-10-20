<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class MenusFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var MenuRepository
     */
    private $menuRepository;

    public function __construct(MenuRepository $menuRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->menuRepository = $menuRepository;
    }

    public function load(ObjectManager $manager)
    {
        $output = new ConsoleOutput();

		$menuLabels = [
			Menu::ACCUEIL,
			Menu::TRACA,
			Menu::QUALI,
			Menu::DEM,
			Menu::ORDRE,
			Menu::STOCK,
			Menu::REFERENTIEL,
			Menu::PARAM,
            Menu::NOMADE
		];

		foreach ($menuLabels as $menuLabel) {
			$menu = $this->menuRepository->findOneBy(['label' => $menuLabel]);

			if (empty($menu)) {
				$menu = new Menu();
				$menu->setLabel($menuLabel);
				$manager->persist($menu);
				$output->writeln('Création du menu "' . $menuLabel . '"');
			}
			$this->addReference('menu-' . $menuLabel, $menu);
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures', 'patch-menus'];
    }
}
