<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\CategorieStatut;

class CategorieStatutFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
            'article',
            'collecte',
            'demande',
            'livraison',
            'preparation',
            'reception', 
            'service'
        ];

        foreach ($categoriesNames as $categorieName) {
            $categorie = new CategorieStatut();
            $categorie->setNom($categorieName);
            $manager->persist($categorie);
            $this->addReference('statut-' . $categorieName, $categorie);
        }

        $manager->flush();
    }

    public function getGroups():array {
        return ['status'];
    }


}
