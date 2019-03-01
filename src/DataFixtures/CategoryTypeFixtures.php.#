<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\CategoryType;

class CategorieTypeFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
            'référence article',
            'fournisseur',
            'emplacement',
        ];

        foreach ($categoriesNames as $categorieName) {
            $categorie = new CategoryType();
            $categorie->setLabel($categorieName);
            $manager->persist($categorie);
            $this->addReference('statut-' . $categorieName, $categorie);
        }

        $manager->flush();
    }

}
