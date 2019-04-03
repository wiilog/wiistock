<?php

namespace App\DataFixtures;

use App\Entity\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TypeFixtures extends Fixture implements DependentFixtureInterface
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
//        categorie referenceArticle
          $typesNames = [
              'PDD',
              'PSS',
              'SILI',
              'MOB'
         ];

         foreach ($typesNames as $typeName) {
             $type = new Type();
             $type
                 ->setLabel($typeName)
                 ->setCategory($this->getReference('type-referenceArticle'));
             $manager->persist($type);
         }


        $manager->flush();


    }

    public function getDependencies()
    {
        return [CategoryTypeFixtures::class];
    }

    public function getGroups():array {
        return ['types'];
    }


}