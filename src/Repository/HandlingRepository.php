<?php

namespace App\Repository;

use App\Entity\Handling;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Handling|null find($id, $lockMode = null, $lockVersion = null)
 * @method Handling|null findOneBy(array $criteria, array $orderBy = null)
 * @method Handling[]    findAll()
 * @method Handling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HandlingRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'number' => 'number',
        'creationDate' => 'creationDate',
        'type' => 'type',
        'requester' => 'requester',
        'subject' => 'subject',
        'desiredDate' => 'desiredDate',
        'validationDate' => 'validationDate',
        'status' => 'status',
        'emergency' => 'emergency'
    ];

    public function countHandlingToTreat(){

        $qb = $this->createQueryBuilder('handling');

        $qb->select('COUNT(handling)')
            ->join('handling.status', 'status')
            ->where('status.treated = false');

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $typeIds
     * @return int|mixed|string
     */
    public function getMobileHandlingsByUserTypes(array $typeIds) {

        $queryBuilder = $this->createQueryBuilder('handling');
        $queryBuilder
            ->select('handling.id AS id')
            ->addSelect('handling.desiredDate AS desiredDate')
            ->addSelect('handling_requester.username AS requester')
            ->addSelect('handling.comment AS comment')
            ->addSelect('handling.source AS source')
            ->addSelect('handling.destination AS destination')
            ->addSelect('handling.subject AS subject')
            ->addSelect('handling.number AS number')
            ->addSelect('handling_type.label AS typeLabel')
            ->addSelect('handling_type.id AS typeId')
            ->leftJoin('handling.requester', 'handling_requester')
            ->leftJoin('handling.status', 'status')
            ->leftJoin('handling.type', 'handling_type')
            ->where('status.treated = false')
            ->andWhere('status.needsMobileSync = true')
            ->andWhere('handling_type.id IN (:userTypes)')
            ->setParameter('userTypes', $typeIds);

        return array_map(
            function (array $handling): array {
                $handling['desiredDate'] = $handling['desiredDate'] ? $handling['desiredDate']->format('d/m/Y H:i:s') : null;
                return $handling;
            },
            $queryBuilder->getQuery()->getResult()
        );
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
            "SELECT COUNT(handling)
            FROM App\Entity\Handling handling
            WHERE handling.requester = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Handling[]|null
     */
    public function findByDates($dateMin, $dateMax)
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT handling
            FROM App\Entity\Handling handling
            WHERE handling.creationDate BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

	public function findByParamAndFilters($params, $filters)
    {
        $qb = $this->createQueryBuilder('handling');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('handling.status', 'status')
						->andWhere('status.id in (:status)')
						->setParameter('status', $value);
					break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('handling.requester', 'requester')
                        ->andWhere("requester.id in (:username)")
                        ->setParameter('username', $value);
                    break;
                case 'type':
                    $qb
                        ->join('handling.type', 'type')
                        ->andWhere("type.label in (:type)")
                        ->setParameter('type', $filter['value']);
                    break;
                case 'dateMin':
                    $qb->andWhere('handling.creationDate >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('handling.creationDate <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
            }
        }

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
                        ->leftJoin('handling.type', 'searchType')
                        ->leftJoin('handling.requester', 'searchRequester')
                        ->leftJoin('handling.status', 'searchStatus')
						->andWhere('
						handling.number LIKE :value
						OR handling.creationDate LIKE :value
						OR searchType.label LIKE :value
						OR searchRequester.username LIKE :value
						OR handling.subject LIKE :value
						OR handling.desiredDate LIKE :value
						OR handling.validationDate LIKE :value
						OR searchStatus.nom LIKE :value
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
                    if ($column === 'number') {
                        $qb
                            ->orderBy('handling.number', $order);
                    } else if ($column === 'creationDate') {
                        $qb
                            ->orderBy('handling.creationDate', $order);
                    }else if ($column === 'type') {
                        $qb
                            ->leftJoin('handling.type', 'type')
                            ->orderBy('type.label', $order);
                    } else if ($column === 'requester') {
                        $qb
                            ->leftJoin('handling.requester', 'requester')
                            ->orderBy('requester.username', $order);
                    } else if ($column === 'subject') {
                        $qb
                            ->orderBy('handling.subject', $order);
                    } else if ($column === 'desiredDate') {
                        $qb
                            ->orderBy('handling.desiredDate', $order);
                    } else if ($column === 'validationDate') {
                        $qb
                            ->orderBy('handling.validationDate', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('handling.status', 'status')
                            ->orderBy('status.nom', $order);
                    } else if ($column === 'emergency') {
                        $qb
                            ->orderBy('handling.emergency', $order);
                    } else {
                        $qb
                            ->orderBy('handling.' . $column, $order);
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

    public function getLastHandlingNumberByPrefix($prefix)
    {
        $queryBuilder = $this->createQueryBuilder('handling');
        $queryBuilder
            ->select('handling.number')
            ->where('handling.number LIKE :value')
            ->orderBy('handling.creationDate', 'DESC')
            ->setParameter('value', $prefix . '%');

        $result = $queryBuilder
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }
}
