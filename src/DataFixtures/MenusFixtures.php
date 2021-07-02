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
        Menu::DASHBOARDS => null,
        Menu::TRACA => null,
        Menu::QUALI => null,
        Menu::DEM => null,
        Menu::ORDRE => null,
        Menu::STOCK => null,
        Menu::REFERENTIEL => null,
        Menu::IOT => "IoT.IoT",
        Menu::PARAM => null,
        Menu::NOMADE => null,
    ];

    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder) {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $menuRepository = $manager->getRepository(Menu::class);

        foreach(self::MENUS as $label => $translation) {
            $menu = $menuRepository->findOneBy(["label" => $label]);

            if(empty($menu)) {
                $menu = new Menu();
                $manager->persist($menu);
                $output->writeln("Created menu \"$label\"");
            }

            // force case update
            $menu->setLabel($label);
            $menu->setTranslation($translation);

            $this->addReference("menu-$label", $menu);
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures', 'patch-menus'];
    }

}
