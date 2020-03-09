<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Fournisseur;
use App\Entity\Urgence;

use DateTime;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends ServiceEntityRepository
{

    private const DtToDbLabels = [
        "start" => 'dateStart',
        "end" => 'dateEnd',
        'arrivalDate' => 'lastArrival',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Urgence::class);
    }

	/**
	 * @param Arrivage $arrivage
	 * @param bool $excludeTriggered
	 * @return Urgence[]
	 */
    public function findUrgencesMatching(Arrivage $arrivage, $excludeTriggered = false): array {
        $queryBuilder = $this->createQueryBuilder('u')
            ->where(':date BETWEEN u.dateStart AND u.dateEnd')
            ->andWhere('u.commande IN (:commande)')
            ->andWhere('u.provider IS NULL OR u.provider = :provider')
            ->setParameters([
                'date' => $arrivage->getDate(),
                'commande' => explode(',', $arrivage->getNumeroBL()),
                'provider' => $arrivage->getFournisseur()
            ]);

        if ($excludeTriggered) {
            $queryBuilder->andWhere('u.lastArrival IS NULL');
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @param DateTime $dateStart
     * @param DateTime $dateEnd
     * @param Fournisseur|null $provider
     * @param string|null $numeroCommande
     * @param array $urgenceIdsExcluded
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countUrgenceMatching(DateTime $dateStart,
                                         DateTime $dateEnd,
                                         ?Fournisseur $provider,
                                         ?string $numeroCommande,
                                         array $urgenceIdsExcluded = []): int {

        $queryBuilder = $this->createQueryBuilder('u');

        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->select('COUNT(u)')
            ->where($exprBuilder->orX(
                ':dateStart BETWEEN u.dateStart AND u.dateEnd',
                ':dateEnd BETWEEN u.dateStart AND u.dateEnd',
                'u.dateStart BETWEEN :dateStart AND :dateEnd',
                'u.dateEnd BETWEEN :dateStart AND :dateEnd'
            ))
            ->andWhere('u.provider = :provider')
            ->andWhere('u.commande = :commande')
            ->setParameters([
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'provider' => $provider,
                'commande' => $numeroCommande,
            ]);

        if (!empty($urgenceIdsExcluded)) {
            $queryBuilder
                ->andWhere('u.id NOT IN (:urgenceIdsExcluded)')
                ->setParameter('urgenceIdsExcluded', $urgenceIdsExcluded, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countUnsolved() {
        $queryBuilder = $this->createQueryBuilder('urgence')
            ->select('COUNT(urgence)')
            ->where('urgence.dateStart < :now')
            ->andWhere('urgence.lastArrival IS NULL')
            ->setParameter('now', new DateTime('now', new DateTimeZone('Europe/Paris')));

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('u')
            ->from('App\Entity\Urgence', 'u');

        $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'commande':
					$qb->andWhere('u.commande = :commande')
						->setParameter('commande', $filter['value']);
					break;
				case 'dateMin':
					$qb->andWhere('u.dateEnd >= :dateMin')
						->setParameter('dateMin', $filter['value'] . " 00:00:00");
					break;
				case 'dateMax':
					$qb->andWhere('u.dateStart <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                	$exprBuilder = $qb->expr();
                    $qb
						->leftJoin('u.buyer', 'b_search')
						->leftJoin('u.provider', 'p_search')
						->leftJoin('u.carrier', 'c_search')
                        ->andWhere($exprBuilder->orX(
                        	'u.commande LIKE :value',
							'u.postNb LIKE :value',
							'u.trackingNb LIKE :value',
							'b_search.username LIKE :value',
							'p_search.nom LIKE :value',
							'c_search.label LIKE :value'
							))
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']] ??
						$params->get('columns')[$params->get('order')[0]['column']]['data'];
                    switch ($column) {
						case 'provider':
							$qb
								->leftJoin('u.provider', 'p_order')
								->orderBy('p_order.nom', $order);
							break;
						case 'carrier':
							$qb
								->leftJoin('u.carrier', 'c_order')
								->orderBy('c_order.label', $order);
							break;
						case 'buyer':
							$qb
								->leftJoin('u.buyer', 'b_order')
								->orderBy('b_order.username', $order);
							break;
						default:
							$qb->orderBy('u.' . $column, $order);
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
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
