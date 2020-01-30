<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

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

	private const DtToDbLabels = [
		'date' => 'datetime',
		'colis' => 'colis',
		'location' => 'emplacement',
		'type' => 'status',
		'operateur' => 'user',
	];

    public function __construct(ManagerRegistry $registry)
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
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return MouvementTraca[]
	 * @throws Exception
	 */
    public function findByDates($dateMin, $dateMax)
    {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

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
	 * @throws NoResultException
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
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('m')
            ->from('App\Entity\MouvementTraca', 'm');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('m.type', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'emplacement':
                    $emplacementValue = explode(':', $filter['value']);
                    $qb
                        ->join('m.emplacement', 'e')
                        ->andWhere('e.label = :location')
                        ->setParameter('location', $emplacementValue[1] ?? $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.operateur', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('m.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('m.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'colis':
                    $qb
                        ->andWhere('m.colis LIKE :colis')
                        ->setParameter('colis', '%' . $filter['value'] . '%');
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('m.emplacement', 'e2')
                        ->leftJoin('m.operateur', 'u2')
                        ->leftJoin('m.type', 's2')
                        ->andWhere('
						m.colis LIKE :value OR
						e2.label LIKE :value OR
						s2.nom LIKE :value OR
						u2.username LIKE :value
						')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

                    if ($column === 'emplacement') {
                        $qb
                            ->leftJoin('m.emplacement', 'e3')
                            ->orderBy('e3.label', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('m.type', 's3')
                            ->orderBy('s3.nom', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('m.operateur', 'u3')
                            ->orderBy('u3.username', $order);
                    } else {
                        $qb
                            ->orderBy('m.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = count($qb->getQuery()->getResult());

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null ,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
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
                     (CASE WHEN m.finished = 1 THEN 1 ELSE 0 END) as finished,
                     (CASE WHEN m.mouvementStock IS NOT NULL THEN 1 ELSE 0 END) as fromStock,
                     mouvementStock.quantity as quantity
            FROM App\Entity\MouvementTraca m
            JOIN m.type t
            JOIN m.operateur o
            JOIN m.emplacement e
            LEFT JOIN m.mouvementStock mouvementStock
            WHERE o = :op
              AND t.nom LIKE :priseType
              AND m.finished = :finished") . $typeCondition
        )
            ->setParameter('op', $operator)
            ->setParameter('priseType', MouvementTraca::TYPE_PRISE)
            ->setParameter('finished', false);
        return $query->execute();
    }

	public function countByEmplacement($emplacementId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            JOIN m.emplacement e
            WHERE e.id = :emplacementId"
		)->setParameter('emplacementId', $emplacementId);
		return $query->getSingleScalarResult();
	}

	/**
	 * @param MouvementStock $mouvementStock
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countByMouvementStock($mouvementStock)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            WHERE m.mouvementStock = :mouvementStock"
		)->setParameter('mouvementStock', $mouvementStock);
		return $query->getSingleScalarResult();
	}
}
