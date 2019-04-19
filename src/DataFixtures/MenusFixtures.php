<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class MenusFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $menusInfos = [
            ['Réception', 'REC'],
            ['Préparation', 'PREPA'],
            ['Livraison', 'LIVR'],
            ['Demande de livraison', 'DEMLIVR'],
            ['Demande de collecte', 'DEMCOL'],
//            ['Collecte', 'COL'],
            ['Manutention', 'MANUT'],
//            ['Utilisation Nomade', 'NOMAD'],
            ['Paramétrage', 'PARAM'],
            ['Stock', 'STOCK'],
        ];
        foreach ($menusInfos as $menuInfos) {
            $menu = new Menu();
            $menu
                ->setLabel($menuInfos[0])
                ->setCode($menuInfos[1]);

            $manager->persist($menu);
            $this->addReference('menu-' . $menuInfos[1], $menu);
        }

        $manager->flush();
    }

}
