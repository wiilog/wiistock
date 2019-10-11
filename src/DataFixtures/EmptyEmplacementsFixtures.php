<?php


namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use Doctrine\ORM\EntityManagerInterface;

class EmptyEmplacementsFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleReposotory;

    private $em;

    public function __construct(EmplacementRepository $emplacementRepository, ReferenceArticleRepository $referenceArticleRepository, EntityManagerInterface $em)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleReposotory = $referenceArticleRepository;
        $this->em = $em;
    }

    public function load(ObjectManager $manager)
    {
        $emplacementDef = $this->emplacementRepository->findOneByLabel('A définir');
        $query = $this->em->createQuery(
        /** @lang DQL */
            "UPDATE App\Entity\ReferenceArticle ra
            SET ra.emplacement = :emplacement
            WHERE ra.emplacement IS NULL"
        )->setParameter('emplacement', $emplacementDef);

        $query->execute();
    }

    public static function getGroups():array {
        return ['emptyLocation'];
    }
}