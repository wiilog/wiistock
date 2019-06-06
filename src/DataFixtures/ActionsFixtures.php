<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Repository\ActionRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Tests\A;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ActionsFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

    public function __construct(ActionRepository $actionRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->actionRepository = $actionRepository;
    }

    public function load(ObjectManager $manager)
    {
        // actions de type lister/créer-modifier/supprimer
        $menus = [
            Menu::RECEPTION,
            Menu::DEM_LIVRAISON,
            Menu::DEM_COLLECTE,
            Menu::STOCK
        ];

        $actionLabels = [Action::LIST, Action::CREATE_EDIT, Action::DELETE];

        foreach ($menus as $menu) {
            foreach ($actionLabels as $actionLabel) {
                $action = $this->actionRepository->findOneByMenuCodeAndLabel($menu, $actionLabel);

                if (empty($action)) {
                    $action = new Action();

                    $action
                        ->setLabel($actionLabel)
                        ->setMenu($this->getReference('menu-' . $menu));
                    $manager->persist($action);
                    dump("création de l'action " . $menu . " / " . $actionLabel);
                }
            }
        }


        // actions de type lister/créer/modifier-supprimer
        $menus = [
            Menu::MANUT
        ];

        $actionLabels = [Action::LIST, Action::CREATE, Action::EDIT_DELETE];

        foreach ($menus as $menu) {
            foreach ($actionLabels as $actionLabel) {
                $action = $this->actionRepository->findOneByMenuCodeAndLabel($menu, $actionLabel);

                if (empty($action)) {
                    $action = new Action();
                    $action
                        ->setLabel($actionLabel)
                        ->setMenu($this->getReference('menu-' . $menu));
                    $manager->persist($action);
                    dump("création de l'action " . $menu . " / " . $actionLabel);
                }
            }
        }


        // actions de type lister/créer-modifier
        $menus = [
            Menu::PREPA,
            Menu::LIVRAISON,
            Menu::COLLECTE
        ];

        $actionLabels = [Action::LIST, Action::CREATE_EDIT];

        foreach ($menus as $menu) {
            foreach ($actionLabels as $actionLabel) {
                $action = $this->actionRepository->findOneByMenuCodeAndLabel($menu, $actionLabel);

                if (empty($action)) {
                    $action = new Action();

                    $action
                        ->setLabel($actionLabel)
                        ->setMenu($this->getReference('menu-' . $menu));
                    $manager->persist($action);
                    dump("création de l'action " . $menu . " / " . $actionLabel);
                }
            }
        }


        // action exporter
        $menus = [
            Menu::DEM_LIVRAISON,
            Menu::STOCK
        ];

        $actionLabel = Action::EXPORT;

        foreach ($menus as $menu) {
            $action = $this->actionRepository->findOneByMenuCodeAndLabel($menu, $actionLabel);

            if (empty($action)) {
                $action = new Action();
                $action
                    ->setLabel($actionLabel)
                    ->setMenu($this->getReference('menu-' . $menu));
                $manager->persist($action);
                dump("création de l'action " . $menu . " / " . $actionLabel);
            }
        }


        // action oui
        $menus = [
            Menu::PARAM,
            Menu::INDICS_ACCUEIL
        ];

        $actionLabel = Action::YES;

        foreach ($menus as $menu) {
            $action = $this->actionRepository->findOneByMenuCodeAndLabel($menu, $actionLabel);

            if (empty($action)) {
                $action = new Action();
                $action
                    ->setLabel(Action::YES)
                    ->setMenu($this->getReference('menu-' . $menu));
                $manager->persist($action);
                dump("création de l'action " . $menu . " / " . $actionLabel);
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
