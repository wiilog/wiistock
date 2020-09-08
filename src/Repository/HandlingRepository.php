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
        'emergency' => 'emergency',
    ];

    public function countByStatut($status){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(handling)
            FROM App\Entity\Handling handling
            WHERE handling.status = :status
           "
            )->setParameter('status', $status);
        return $query->getSingleScalarResult();
    }

    public function findByStatut($status) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @Lang DQL */
            "SELECT handling.id, handling.desiredDate as desiredDate, requester.username as requester, handling.comment, handling.source, handling.destination, handling.subject as objet
        FROM App\Entity\Handling handling
        JOIN handling.requester requester
        WHERE handling.status = :status
        "
        )->setParameter('$status', $status);
        return $query->execute();
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
                case 'status':
					$value = explode(',', $filter['value']);
					$qb
						->join('handling.status', 'status')
						->andWhere('status.id in (:status)')
						->setParameter('status', $value);
					break;
                case 'requesters':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('handling.requester', 'requester')
                        ->andWhere("requester.id in (:username)")
                        ->setParameter('username', $value);
                    break;
                case 'types':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('handling.type', 'type')
                        ->andWhere("type.id in (:type)")
                        ->setParameter('type', $value);
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
						->andWhere('handling.subject LIKE :value OR handling.creationDate LIKE :value')
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
                            ->leftJoin('handling.subject', 'subject')
                            ->orderBy('subject.username', $order);
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
