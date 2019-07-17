<?php

namespace App\DataFixtures;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
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
        $menusInfos = [
            ['Réception', 'REC'],
            ['Préparation', 'PREPA'],
            ['Livraison', 'LIVR'],
            ['Demande de livraison', 'DEMLIVR'],
            ['Demande de collecte', 'DEMCOL'],
            ['Collecte', 'COL'],
            ['Manutention', 'MANUT'],
            ['Paramétrage', 'PARAM'],
            ['Stock', 'STOCK'],
            ['Indicateurs accueil', 'INDICAC'],
            ['Arrivage', 'ARRIVAGE']
        ];
        foreach ($menusInfos as $menuInfos) {
            $menu = $this->menuRepository->findOneBy(['code' => $menuInfos[1]]);

            if (empty($menu)) {
                $menu = new Menu();
                $menu
                    ->setLabel($menuInfos[0])
                    ->setCode($menuInfos[1]);

                $manager->persist($menu);
                dump("création du menu " . $menuInfos[1]);
            }
            $this->addReference('menu-' . $menuInfos[1], $menu);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['actions', 'fixtures'];
    }
}
