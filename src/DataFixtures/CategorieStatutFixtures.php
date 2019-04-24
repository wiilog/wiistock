<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\CategorieStatut;

class CategorieStatutFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
            'referenceArticle',
            'article',
            'collecte',
            'ordreCollecte',
            'demande',
            'livraison',
            'preparation',
            'reception',
            'manutention'
        ];

        foreach ($categoriesNames as $categorieName) {
            $categorie = new CategorieStatut();
            $categorie->setNom($categorieName);
            $manager->persist($categorie);
            $this->addReference('statut-' . $categorieName, $categorie);
        }

        $manager->flush();
    }

    public static function getGroups():array {
        return ['status'];
    }


}
