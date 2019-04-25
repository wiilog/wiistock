<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategoryType;

class CategoryTypeFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }
    
    public function load(ObjectManager $manager)
    {
        $categoriesNames = [
           'typeArticle'
        ];
        foreach ($categoriesNames as $categorieName) {
            $categorie = new CategoryType();
            $categorie->setLabel($categorieName);
            $manager->persist($categorie);
            $this->addReference('type-' . $categorieName, $categorie);
        }
        $manager->flush();
    }

    public static function getGroups():array {
        return ['types'];
    }

}
