<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\MouvementTraca;
use App\Entity\Utilisateur;
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

    public const MOUVEMENT_TRACA_DEFAULT = 'tracking';
    public const MOUVEMENT_TRACA_STOCK = 'stock';

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MouvementTraca::class);
    }

    /**
     * @param $uniqueId
     * @return MouvementTraca
     * @throws NonUniqueResultException
     */
    public function findOneByUniqueIdForMobile($uniqueId) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
			'SELECT mvt
                FROM App\Entity\MouvementTraca mvt
                WHERE mvt.uniqueIdForMobile = :uniqueId'
        )->setParameter('uniqueId', $uniqueId);
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
        	/** @lang DQL */
            'SELECT m
            FROM App\Entity\MouvementTraca m
            WHERE m.datetime BETWEEN :dateMin AND :dateMax'
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
			WHERE mt.colis = :colis
			ORDER BY mt.datetime DESC"
		)->setParameter('colis', $colis);

		$result = $query->execute();
		return $result ? $result[0] : null;
	}

    //VERIFCECILE
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
            JOIN m.type t
            WHERE m.emplacement = :emp AND m.datetime > :date AND m.colis LIKE :article AND t.nom LIKE 'prise'"
        )->setParameters([
            'emp' => $emplacement,
            'date' => $mvt->getDatetime(),
            'article' => $mvt->getColis(),
        ]);
        return $query->getSingleScalarResult();
    }

    //VERIFCECILE
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
            JOIN m.type t
            WHERE m.emplacement = :emp AND t.nom LIKE 'depose'"
        )->setParameter('emp', $emplacement);
        return $query->execute();
    }

    /**
     * @param Utilisateur $operator
     * @param string $type self::MOUVEMENT_TRACA_STOCK | self::MOUVEMENT_TRACA_DEFAULT
     * @return MouvementTraca[]
     */
    public function getTakingByOperatorAndNotDeposed(Utilisateur $operator, string $type) {
        $em = $this->getEntityManager();
        $typeCondition = ($type === self::MOUVEMENT_TRACA_STOCK)
            ? ' AND m.mouvementStock IS NOT NULL'
            : ' AND m.mouvementStock IS NULL'; // MOUVEMENT_TRACA_DEFAULT
        $query = $em->createQuery(
            (/** @lang DQL */
            "SELECT m.colis as ref_article,
                     t.nom as type,
                     o.username as operateur,
                     e.label as ref_emplacement,
                     m.uniqueIdForMobile as date,
                     (CASE WHEN m.finished = 1 THEN 1 ELSE 0 END) as finished
            FROM App\Entity\MouvementTraca m
            JOIN m.type t
            JOIN m.operateur o
            JOIN m.emplacement e
            WHERE o = :op
              AND t.nom LIKE 'prise'
              AND m.finished = 0") . $typeCondition
        )->setParameter('op', $operator);
        return $query->execute();
    }
}
