<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Fournisseur;
use App\Entity\Urgence;
use App\Helper\QueryBuilderHelper;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends EntityRepository {

    private const DtToDbLabels = [
        "start" => 'dateStart',
        "end" => 'dateEnd',
        'arrivalDate' => 'lastArrival',
        'arrivalNb' => 'arrivalNb',
        'createdAt' => 'createdAt',
    ];

    /**
     * @return Urgence[]
     */
    public function findUrgencesMatching(array $fields,
                                         Arrivage $arrival,
                                         ?string $numeroCommande,
                                         ?string $postNb,
                                         $excludeTriggered = false): array {
        $queryBuilder = $this->createQueryBuilder('u')
            ->where(':date BETWEEN u.dateStart AND u.dateEnd')
            ->setParameter('date', $arrival->getDate());

        $values = [
            'provider' => $arrival->getFournisseur(),
            'carrier' => $arrival->getTransporteur(),
            'commande' => $numeroCommande,
        ];

        foreach ($fields as $field) {
            if(!empty($values[$field])) {
                $queryBuilder->andWhere("u.$field = :$field")
                    ->setParameter("$field", $values[$field]);
            }
        }

        if (!empty($postNb)) {
            $queryBuilder
                ->andWhere('u.postNb = :postNb')
                ->setParameter('postNb', $postNb);
        }

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
     * @param bool $daily
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function countUnsolved(bool $daily = false) {
        $queryBuilder = $this->createQueryBuilder('urgence')
            ->select('COUNT(urgence)')
            ->where('urgence.dateStart < :now')
            ->andWhere('urgence.lastArrival IS NULL')
            ->setParameter('now', new DateTime('now'));

        if ($daily) {
            $todayEvening = new DateTime('now');
            $todayEvening->setTime(23, 59, 59, 59);
            $todayMorning = new DateTime('now');
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

    public function findByParamsAndFilters(InputBag $params, $filters) {
        $qb = $this->createQueryBuilder("u");

        $countTotal = QueryBuilderHelper::count($qb, 'u');

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
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->leftJoin('u.buyer', 'b_search')
                        ->leftJoin('u.provider', 'p_search')
                        ->leftJoin('u.carrier', 'c_search')
                        ->leftJoin('u.lastArrival', 'a_search')
                        ->andWhere('(' . $exprBuilder->orX(
                                'u.commande LIKE :value',
                                'u.postNb LIKE :value',
                                'u.trackingNb LIKE :value',
                                'b_search.username LIKE :value',
                                'p_search.nom LIKE :value',
                                'c_search.label LIKE :value',
                                'a_search.numeroArrivage LIKE :value',
                                'u.type LIKE :value',
                            ) . ')')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->all('columns')[$params->all('order')[0]['column']]['data']] ??
                        $params->all('columns')[$params->all('order')[0]['column']]['data'];
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
        $countFiltered = QueryBuilderHelper::count($qb, 'u');

        if ($params->getInt('start')) $qb->setFirstResult($params->getInt('start'));
        if ($params->getInt('length')) $qb->setMaxResults($params->getInt('length'));

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
     */
    public function iterateByDates($dateMin, $dateMax) {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $iterator = $this->createQueryBuilder('u')
            ->where('u.dateEnd >= :dateMin')
            ->andWhere('u.dateStart <= :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->getQuery()
            ->iterate();

        foreach ($iterator as $item) {
            // $item [index => urgence]
            yield array_pop($item);
        }
    }

}
