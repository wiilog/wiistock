<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use App\Entity\Type;
use App\Repository\TypeRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TypeFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private $encoder;

    /**
     * @var TypeRepository
     */
    private $typeRepository;


    public function __construct(TypeRepository $typeRepository, UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
        $this->typeRepository = $typeRepository;
    }

    public function load(ObjectManager $manager)
    {
        // categorie articles et références CEA
        $typesNames = [
            Type::LABEL_PDT,
            Type::LABEL_CSP,
            Type::LABEL_SILI,
            Type::LABEL_SILI_INT,
            Type::LABEL_SILI_EXT,
            Type::LABEL_MOB,
            Type::LABEL_SLUGCIBLE
        ];

        foreach ($typesNames as $typeName) {
            $type = $this->typeRepository->findOneBy(['label' => $typeName]);

            if (empty($type)) {
                $type = new Type();
                $type
                    ->setCategory($this->getReference('type-' . CategoryType::TYPE_ARTICLES_ET_REF_CEA))
                    ->setLabel($typeName);
                $manager->persist($type);
                dump("création du type " . $typeName);
            }
        }

        // catégorie réception
        $type = $this->typeRepository->findOneBy(['label' => Type::LABEL_RECEPTION]);

        if (empty($type)) {
            $type = new Type();
            $type
                ->setCategory($this->getReference('type-' . CategoryType::TYPE_RECEPTION))
                ->setLabel(Type::LABEL_RECEPTION);
            $manager->persist($type);
            dump("création du type " . CategoryType::TYPE_RECEPTION);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [CategoryTypeFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['types', 'fixtures'];
    }
}
