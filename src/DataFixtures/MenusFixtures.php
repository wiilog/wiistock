<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class MenusFixtures extends Fixture implements FixtureGroupInterface {

    const MENUS = [
        Menu::DASHBOARDS,
        Menu::TRACA,
        Menu::QUALI,
        Menu::DEM,
        Menu::ORDRE,
        Menu::STOCK,
        Menu::REFERENTIEL,
        Menu::PARAM,
        Menu::NOMADE
    ];

    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder) {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $menuRepository = $manager->getRepository(Menu::class);

        foreach(self::MENUS as $label) {
            $menu = $menuRepository->findOneBy(["label" => $label]);

            if(empty($menu)) {
                $menu = new Menu();
                $manager->persist($menu);
                $output->writeln("Created menu \"$label\"");
            }

            // force case update
            $menu->setLabel($label);

            $this->addReference("menu-$label", $menu);
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures', 'patch-menus'];
    }

}
