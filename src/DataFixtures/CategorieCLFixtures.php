<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategorieCL;

class CategorieCLFixtures extends Fixture
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
            'aucune'
        ];
        foreach ($categoriesNames as $categorieName) {
            $categorie = new CategorieCL();
            $categorie->setLabel($categorieName);
            $manager->persist($categorie);
        }
        $manager->flush();
    }

}