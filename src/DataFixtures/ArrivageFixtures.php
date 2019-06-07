<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Litige;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
use App\Repository\ActionRepository;
use App\Repository\CategorieStatutRepository;
use App\Repository\CategoryTypeRepository;
use App\Repository\MenuRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ArrivageFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var MenuRepository
     */
    private $menuRepository;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var CategoryTypeRepository
     */
    private $categoryTypeRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var CategorieStatutRepository
     */
    private $categorieStatutRepository;


    public function __construct(StatutRepository $statutRepository, CategorieStatutRepository $categorieStatutRepository, CategoryTypeRepository $categoryTypeRepository, TypeRepository $typeRepository, MenuRepository $menuRepository, ActionRepository $actionRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->actionRepository = $actionRepository;
        $this->menuRepository = $menuRepository;
        $this->typeRepository = $typeRepository;
        $this->categoryTypeRepository = $categoryTypeRepository;
        $this->statutRepository = $statutRepository;
        $this->categorieStatutRepository = $categorieStatutRepository;
    }

    public function load(ObjectManager $manager)
    {
        // menu arrivage
        $menu = $this->menuRepository->findOneBy(['code' => Menu::ARRIVAGE]);

        if (empty($menu)) {
            $menu = new Menu();
            $menu
                ->setLabel('Arrivage')
                ->setCode(Menu::ARRIVAGE);

            $manager->persist($menu);
            dump("création du menu Arrivage");
        }
        $this->addReference('menu-arrivage', $menu);


        // actions liées au menu arrivage
        $actionLabels = [Action::LIST, Action::LIST_ALL, Action::CREATE_EDIT, Action::DELETE];

        foreach ($actionLabels as $actionLabel) {
            $action = $this->actionRepository->findOneByMenuCodeAndLabel(Menu::ARRIVAGE, $actionLabel);

            if (empty($action)) {
                $action = new Action();

                $action
                    ->setLabel($actionLabel)
                    ->setMenu($this->getReference('menu-arrivage'));
                $manager->persist($action);
                dump("création de l'action arrivage / " . $actionLabel);
            }
        }

        // catégorie type litige
        $categorie = $this->categoryTypeRepository->findOneBy(['label' => CategoryType::LITIGE]);

        if (empty($categorie)) {
            $categorie = new CategoryType();
            $categorie->setLabel(CategoryType::LITIGE);
            $manager->persist($categorie);
            $this->addReference('type-litige', $categorie);
            dump("création de la catégorie de type litige");
        }

        // types liés à la catégorie arrivage
        $typesNames = [
            Type::LABEL_MANQUE_BL,
            Type::LABEL_MANQUE_INFO_BL,
            Type::LABEL_ECART_QTE,
            Type::LABEL_ECART_QUALITE,
            Type::LABEL_PB_COMMANDE,
            Type::LABEL_DEST_NON_IDENT
        ];

        foreach ($typesNames as $typeName) {
            $type = $this->typeRepository->findOneBy(['label' => $typeName]);

            if (empty($type)) {
                $type = new Type();
                $type
                    ->setCategory($this->getReference('type-litige'))
                    ->setLabel($typeName);
                $manager->persist($type);
                dump("création du type " . $typeName);
            }
        }

        // catégorie statut arrivage
        $categorie = $this->categorieStatutRepository->findOneBy(['nom' => CategorieStatut::ARRIVAGE]);

        if (empty($categorie)) {
            $categorie = new CategorieStatut();
            $categorie->setNom(CategorieStatut::ARRIVAGE);
            $manager->persist($categorie);
            dump("création de la catégorie de statut arrivage");
        }
        $this->addReference('statut-arrivage', $categorie);

        // statuts liés à la catégorie arrivage
        $statutsNames = [
            Statut::CONFORME,
            Statut::ATTENTE_ACHETEUR,
            Statut::TRAITE_ACHETEUR,
            Statut::SOLDE,
        ];

        foreach ($statutsNames as $statutName) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARRIVAGE, $statutName);

            if (empty($statut)) {
                $statut = new Statut();
                $statut
                    ->setNom($statutName)
                    ->setCategorie($this->getReference('statut-arrivage'));
                $manager->persist($statut);
                dump("création du statut " . $statutName);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['arrivage'];
    }
}
