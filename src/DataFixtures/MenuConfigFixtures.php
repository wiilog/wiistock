<?php

namespace App\DataFixtures;

use App\Entity\MenuConfig;
use App\Repository\MenuConfigRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class MenuConfigFixtures extends Fixture implements FixtureGroupInterface
{
    private $menuConfigRepository;

    public function __construct(MenuConfigRepository $menuConfigRepository)
    {
        $this->menuConfigRepository = $menuConfigRepository;
    }

    public function load(ObjectManager $manager)
    {
		$menusAndSubmenus = MenuConfig::SUBMENUS;

		foreach ($menusAndSubmenus as $menu => $submenus) {
			foreach ($submenus as $submenu) {
				$menuConfig = $this->menuConfigRepository->findOneByMenuAndSubmenu($menu, $submenu);

				if (empty($menuConfig)) {
					$menuConfig = new MenuConfig();
					$menuConfig
						->setMenu($menu)
						->setSubmenu($submenu)
						->setDisplay(true);
					$manager->persist($menuConfig);
					dump('création du paramètre menu config ' . $menu . ' / ' . $submenu);
				}
			}
		}

		$manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fixtures'];
    }
}