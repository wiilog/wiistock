<?php

namespace App\DataFixtures;

use App\Entity\Action;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ActionsFixtures extends Fixture implements DependentFixtureInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        // actions de type lister/créer/supprimer
        $menus = [
            'REC',
            'DEMLIVR',
            'DEMCOL',
            'STOCK',
            'MANUT'
        ];

        $actionLabels = ['lister', 'créer', 'supprimer'];

        foreach ($menus as $menu) {
            foreach ($actionLabels as $actionLabel) {
                $action = new Action();

                $action
                    ->setLabel($actionLabel)
                    ->setMenu($this->getReference('menu-' . $menu));
                $manager->persist($action);
            }
        }

        // actions de type lister/créer
        $menus = [
            'PREPA',
            'LIVR',
            'COL'
        ];

        $actionLabels = ['lister', 'créer'];

        foreach ($menus as $menu) {
            foreach ($actionLabels as $actionLabel) {
                $action = new Action();

                $action
                    ->setLabel($actionLabel)
                    ->setMenu($this->getReference('menu-' . $menu));
                $manager->persist($action);
            }
        }


        // actions de type oui
        $menus = [
//            'NOMAD',
            'PARAM',
        ];

        foreach ($menus as $menu) {
            $action = new Action();

            $action
                ->setLabel('oui')
                ->setMenu($this->getReference('menu-' . $menu));
            $manager->persist($action);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [MenusFixtures::class];
    }

}
