<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method MouvementTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementTraca[]    findAll()
 * @method MouvementTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementTracaRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MouvementTraca::class);
    }

    public function getOneByDate($date) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            'SELECT mvt
                FROM App\Entity\MouvementTraca mvt
                WHERE mvt.date = :date'
        )->setParameter('date', $date);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param $dateMin
	 * @param $dateMax
	 * @return MouvementTraca[]
	 * @throws \Exception
	 */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMinDate = new \DateTime($dateMin);
        $dateMaxDate = new \DateTime($dateMax);
        $dateMaxDate->modify('+1 day');
        $dateMinDate->modify('-1 day');
        $dateMax = $dateMaxDate->format('Y-m-d H:i:s');
        $dateMin = $dateMinDate->format('Y-m-d H:i:s');
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT m
            FROM App\Entity\MouvementTraca m
            WHERE m.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

	/**
	 * @param string $colis
	 * @return  MouvementTraca
	 */
    public function getLastByColis($colis)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT mt
			FROM App\Entity\MouvementTraca mt
			WHERE mt.refArticle = :colis
			ORDER BY mt.date DESC"
		)->setParameter('colis', $colis);

		$result = $query->execute();
		return $result ? $result[0] : null;
	}

    /**
     * @param $emplacement Emplacement
     * @param $mvt MouvementTraca
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findByEmplacementToAndArticleAndDate($emplacement, $mvt) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            WHERE m.refEmplacement = :emp AND m.date > :date AND m.refArticle LIKE :article AND m.type LIKE 'prise'"
        )->setParameters([
            'emp' => $emplacement->getLabel(),
            'date' => $mvt->getDate(),
            'article' => $mvt->getRefArticle(),
        ]);
        return $query->getSingleScalarResult();
    }

    /**
     * @param $emplacement Emplacement
     * @return MouvementTraca[]
     */
    public function findByEmplacementTo($emplacement) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementTraca m
            WHERE m.refEmplacement LIKE :emp AND m.type LIKE 'depose'"
        )->setParameter('emp', $emplacement->getLabel());
        return $query->execute();
    }
}
