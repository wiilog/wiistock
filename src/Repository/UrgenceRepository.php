<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use App\Entity\Urgence;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends EntityRepository
{

    private const DtToDbLabels = [
        "start" => 'dateStart',
        "end" => 'dateEnd',
        'arrivalDate' => 'lastArrival',
        'arrivalNb' => 'arrivalNb',
    ];

    /**
     * @param DateTime $arrivalDate
     * @param Fournisseur $arrivalProvider
     * @param string $numeroCommande
     * @param string|null $postNb
     * @param bool $excludeTriggered
     * @return Urgence[]
     */
    public function findUrgencesMatching(DateTime $arrivalDate,
                                         ?Fournisseur $arrivalProvider,
                                         ?string $numeroCommande,
                                         ?string $postNb,
                                         $excludeTriggered = false): array
    {
        $res = [];
        if (!empty($arrivalProvider)
            && !empty($numeroCommande)) {
            $queryBuilder = $this->createQueryBuilder('u')
                ->where(':date BETWEEN u.dateStart AND u.dateEnd')
                ->andWhere('u.commande = :numeroCommande')
                ->andWhere('u.provider IS NULL OR u.provider = :provider')
                ->setParameter('date', $arrivalDate)
                ->setParameter('provider', $arrivalProvider)
                ->setParameter('numeroCommande', $numeroCommande);

            if (!empty($postNb)) {
                $queryBuilder
                    ->andWhere('u.postNb = :postNb')
                    ->setParameter('postNb', $postNb);
            }

            if ($excludeTriggered) {
                $queryBuilder->andWhere('u.lastArrival IS NULL');
            }
            $res = $queryBuilder
                ->getQuery()
                ->getResult();
        }

        return $res;
    }

    /**
     * @param DateTime $dateStart
     * @param DateTime $dateEnd
     * @param Fournisseur|null $provider
     * @param string|null $numeroCommande
     * @param string|null $numeroPoste
     * @param array $urgenceIdsExcluded
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countUrgenceMatching(DateTime $dateStart,
                                         DateTime $dateEnd,
                                         ?Fournisseur $provider,
                                         ?string $numeroCommande,
                                         ?string $numeroPoste,
                                         array $urgenceIdsExcluded = []): int
    {

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
            ->setParameter('dateStart', $dateStart)
            ->setParameter('dateEnd', $dateEnd)
            ->setParameter('provider', $provider)
            ->setParameter('commande', $numeroCommande);

        if (!empty($numeroPoste)) {
            $queryBuilder
                ->andWhere('u.postNb = :postNb')
                ->setParameter('postNb', $numeroPoste);
        }

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
     * @throws \Exception
     */
    public function countUnsolved(bool $daily = false)
    {
        $queryBuilder = $this->createQueryBuilder('urgence')
            ->select('COUNT(urgence)')
            ->where('urgence.dateStart < :now')
            ->andWhere('urgence.lastArrival IS NULL')
            ->setParameter('now', new DateTime('now', new DateTimeZone('Europe/Paris')));
        if ($daily) {
            $todayEvening = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $todayEvening->setTime(23, 59, 59, 59);
            $todayMorning = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $todayMorning->setTime(0, 0, 0, 1);
            $queryBuilder
                ->andWhere('urgence.dateEnd < :todayEvening')
                ->andWhere('urgence.dateEnd > :todayMorning')
                ->setParameter('todayEvening', $todayEvening)
                ->setParameter('todayMorning', $todayMorning);
        }
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
                        ->leftJoin('u.lastArrival', 'a_search' )
                        ->andWhere($exprBuilder->orX(
                            'u.commande LIKE :value',
                            'u.postNb LIKE :value',
                            'u.trackingNb LIKE :value',
                            'b_search.username LIKE :value',
                            'p_search.nom LIKE :value',
                            'c_search.label LIKE :value',
                            'a_search.numeroArrivage LIKE :value'
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
                        case 'arrivalNb':
                            $qb
                                ->leftJoin('u.lastArrival', 'a_order')
                                ->orderBy('a_order.numeroArrivage', $order);
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


    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Urgence[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $qb
            ->select('u')
            ->from('App\Entity\Urgence ', 'u')
            ->where('u.dateStart > :dateMin' )
           ->andWhere('u.dateEnd < :dateMax')
        ->setParameters([
        'dateMin' => $dateMin,
        'dateMax' => $dateMax
    ]);
        return $qb->getQuery()->getResult();
    }
}
