<?php

namespace App\DataFixtures;

use App\Entity\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TypeFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        // categorie typeArticle
        $typesNames = [
            'PDT',
            'CSP',
            'PSS',
            'SILI',
            'MOB',
            'SLUGCIBLE',
            'ARTICLE'
        ];

        foreach ($typesNames as $typeName) {
            $type = new Type();
            $type->setCategory($this->getReference('type-typeArticle'));
            $type->setLabel($typeName);
            $manager->persist($type);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [CategoryTypeFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['types'];
    }
}
