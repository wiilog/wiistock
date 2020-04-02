<?php


namespace App\DataFixtures;

use App\Entity\Emplacement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;

class PatchEmptyEmplacementsFixtures extends Fixture implements FixtureGroupInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function load(ObjectManager $manager)
    {
        $emplacementRepository = $manager->getRepository(Emplacement::class);
        $emplacementDef = $emplacementRepository->findOneByLabel('A définir');

        if (!$emplacementDef) {
        	$emplacementDef = new Emplacement();
        	$emplacementDef->setLabel('A définir')
				->setDescription('sans emplacement')
				->setIsDeliveryPoint(false)
				->setIsActive(true);
		}
        $manager->flush();
		$emplacementDef = $emplacementRepository->findOneByLabel('A définir');

		$query = $this->em->createQuery(
		/** @lang DQL */
			"UPDATE App\Entity\ReferenceArticle ra
		SET ra.emplacement = :emplacement
		WHERE ra.emplacement IS NULL"
		)->setParameter('emplacement', $emplacementDef);

		$query->execute();

		$query = $this->em->createQuery(
		/** @lang DQL */
			"UPDATE App\Entity\Article a
		SET a.emplacement = :emplacement
		WHERE a.emplacement IS NULL"
		)->setParameter('emplacement', $emplacementDef);

		$query->execute();

    }

    public static function getGroups():array {
        return ['empty-location'];
    }
}
