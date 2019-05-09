<?php

namespace App\DataFixtures;

use App\Entity\CategorieCL;
use App\Entity\Statut;
use App\Repository\CategorieStatutRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation\Category;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\CategorieStatut;

class CategorieStatutFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var CategorieStatutRepository
     */
    private $categorieStatutRepository;

    public function __construct(CategorieStatutRepository $categorieStatutRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->categorieStatutRepository = $categorieStatutRepository;
    }

    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
            CategorieStatut::REFERENCE_ARTICLE,
            CategorieStatut::ARTICLE,
            CategorieStatut::COLLECTE,
            CategorieStatut::ORDRE_COLLECTE,
            CategorieStatut::DEMANDE,
            CategorieStatut::LIVRAISON,
            CategorieStatut::PREPARATION,
            CategorieStatut::RECEPTION,
            CategorieStatut::MANUTENTION
        ];

        foreach ($categoriesNames as $categorieName) {
            $categorie = $this->categorieStatutRepository->findOneBy(['nom' => $categorieName]);

            if (empty($categorie)) {
                $categorie = new CategorieStatut();
                $categorie->setNom($categorieName);
                $manager->persist($categorie);
                dump("création de la catégorie " . $categorieName);
            }
            $this->addReference('statut-' . $categorieName, $categorie);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['status', 'fixtures'];
    }
}
