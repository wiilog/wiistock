<?php

namespace App\Repository;

use App\Entity\Acheminements;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;


/**
 * @method Acheminements|null find($id, $lockMode = null, $lockVersion = null)
 * @method Acheminements|null findOneBy(array $criteria, array $orderBy = null)
 * @method Acheminements[]    findAll()
 * @method Acheminements[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AcheminementsRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'Numero' => 'number',
        'Date' => 'date',
        'Type' => 'type',
        'Demandeur' => 'requester',
        'Destinataire' => 'receiver',
        'Emplacement prise' => 'locationTake',
        'Emplacement de dépose' => 'locationDrop',
        'Nb Colis' => 'colis',
        'Statut' => 'statut',
    ];

    public function findByParamAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder('a');

        $countTotal = $qb
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $value = explode(',', $filter['value']);
                    dump($filter);
					$qb
						->join('a.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case FiltreSup::FIELD_MULTIPLE_TYPES:
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.type', 't')
                        ->andWhere('t.id in (:type)')
                        ->setParameter('type', $value);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('a.requester', 'r')
                        ->andWhere('r.id in (:requester)')
                        ->setParameter('requester', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('a.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $qb->andWhere('a.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
            }
        }
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('a.packs LIKE :value OR a.date LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    if ($column === 'statut') {
                        $qb
                            ->leftJoin('a.statut', 's2')
                            ->orderBy('s2.nom', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('a.requester', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'receiver') {
                        $qb
                            ->leftJoin('a.receiver', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'type') {
                        $qb
                            ->leftJoin('a.type', 't2')
                            ->orderBy('t2.label', $order);
                    }else {
                        $qb
                            ->orderBy('a.' . $column, $order);
                    }
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = $qb
            ->getQuery()
            ->getSingleScalarResult();

        $qb
            ->select('a');

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
     * @param Utilisateur $user
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByUser($user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Acheminements a
            WHERE a.receiver = :user"
        )->setParameter('user', $user);

        return $query->getSingleScalarResult();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Acheminements a
            WHERE a.locationFrom = :emplacementId
            OR a.locationTo = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    public function getLastDispatchNumberByDate($date)
    {
        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch.number as number')
            ->where('dispatch.number LIKE :value')
            ->orderBy('dispatch.date', 'DESC')
            ->setParameter('value', $date . '%');

        $result = $queryBuilder
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function getMobileDispatches(Utilisateur $user)
    {
        $queryBuilder = $this->createQueryBuilder('dispatch');
        $queryBuilder
            ->select('dispatch_requester.username AS requester')
            ->addSelect('dispatch.id AS id')
            ->addSelect('dispatch.number AS number')
            ->addSelect('dispatch.startDate AS startDate')
            ->addSelect('dispatch.endDate AS endDate')
            ->addSelect('dispatch.urgent AS urgent')
            ->addSelect('locationFrom.label AS locationFromLabel')
            ->addSelect('locationTo.label AS locationToLabel')
            ->addSelect('type.label AS typeLabel')
            ->addSelect('status.nom AS statusLabel')
            ->join('dispatch.requester', 'dispatch_requester')
            ->leftJoin('dispatch.locationFrom', 'locationFrom')
            ->leftJoin('dispatch.locationTo', 'locationTo')
            ->join('dispatch.type', 'type')
            ->join('dispatch.statut', 'status')
            ->where('status.needsMobileSync = 1')
            ->andWhere('status.treated = 0')
            ->andWhere('type.id IN (:dispatchTypeIds)')
            ->setParameter('dispatchTypeIds', $user->getDispatchTypeIds());

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
