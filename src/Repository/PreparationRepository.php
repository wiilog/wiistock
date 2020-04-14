<?php

namespace App\Repository;

use App\Entity\FiltreSup;
use App\Entity\Preparation;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends ServiceEntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'status',
		'Date' => 'date',
		'Opérateur' => 'user',
		'Type' => 'type'
	];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Preparation::class);
    }

    public function getByDemande($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT p
           FROM App\Entity\Preparation p
           JOIN p.demande d
           WHERE d.id = :id "
        )->setParameter('id', $id);

        return $query->execute();
    }

    public function getAvailablePreparations($user, array $preparationIdsFilter = [])
    {
        $userTypes = [];
        foreach ($user->getTypes() as $type) {
            $userTypes[] = $type->getId();
        }

        $queryBuilder = $this->createQueryBuilder('p');
        $queryBuilder
            ->select('p.id')
            ->addSelect('p.numero as number')
            ->addSelect('dest.label as destination')
            ->addSelect('user.username as requester')
            ->addSelect('t.label as type')
            ->join('p.statut', 's')
            ->join('p.demande', 'd')
            ->join('d.destination', 'dest')
            ->join('d.type', 't')
            ->join('d.utilisateur', 'user')
            ->andWhere('s.nom = :statusLabel or (s.nom = :enCours AND p.utilisateur = :user)')
            ->andWhere('t.id IN (:type)')
            ->setParameters([
                'statusLabel' => Preparation::STATUT_A_TRAITER,
                'user' => $user,
                'enCours' => Preparation::STATUT_EN_COURS_DE_PREPARATION,
                'type' => $userTypes,
            ]);

        if (!empty($preparationIdsFilter)) {
            $queryBuilder
                ->andWhere('p.id IN (:preparationIdsFilter)')
                ->setParameter('preparationIdsFilter', $preparationIdsFilter, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
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
            "SELECT COUNT(p)
            FROM App\Entity\Preparation p
            WHERE p.utilisateur = :user"
        )->setParameter('user', $user);

        return $query->getSingleScalarResult();
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
			->select('p')
			->from('App\Entity\Preparation', 'p');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch($filter['field']) {
				case FiltreSup::FIELD_TYPE:
					$qb
						->leftJoin('p.demande', 'd')
						->leftJoin('d.type', 't')
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case FiltreSup::FIELD_STATUT:
					$value = explode(',', $filter['value']);
					$qb
						->join('p.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
				case FiltreSup::FIELD_USERS:
					$value = explode(',', $filter['value']);
					$qb
						->join('p.utilisateur', 'u')
						->andWhere("u.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case FiltreSup::FIELD_DATE_MIN:
					$qb
						->andWhere('p.date >= :dateMin')
						->setParameter('dateMin', $filter['value']. " 00:00:00");
					break;
				case FiltreSup::FIELD_DATE_MAX:
					$qb
						->andWhere('p.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
                case FiltreSup::FIELD_DEMANDE:
                    $qb
                        ->join('p.demande', 'demande')
                        ->andWhere('demande.id = :id')
                        ->setParameter('id', $filter['value']);
                    break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('p.demande', 'd2')
						->leftJoin('d2.type', 't2')
						->leftJoin('p.utilisateur', 'p2')
						->leftJoin('p.statut', 's2')
						->andWhere('
						p.numero LIKE :value OR
						t2.label LIKE :value OR
						p2.username LIKE :value OR
						s2.nom LIKE :value
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

					if ($column === 'status') {
						$qb
							->leftJoin('p.statut', 's3')
							->orderBy('s3.nom', $order);
					} else if ($column === 'type') {
						$qb
							->leftJoin('p.demande', 'd3')
							->leftJoin('d3.type', 't3')
							->orderBy('t3.label', $order);
					} else if ($column === 'user') {
						$qb
							->leftJoin('p.utilisateur', 'u3')
							->orderBy('u3.username', $order);
					} else {
						$qb
							->orderBy('p.' . $column, $order);
					}
				}
			}
		}

		// compte éléments filtrés
		$countFiltered = count($qb->getQuery()->getResult());

		if ($params) {
			if (!empty($params->get('start'))) {
			    $qb->setFirstResult($params->get('start'));
            }
			if (!empty($params->get('length'))) {
			    $qb->setMaxResults($params->get('length'));
            }
		}

		$query = $qb->getQuery();

		return [
			'data' => $query ? $query->getResult() : null ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return Preparation[]|null
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT p
            FROM App\Entity\Preparation p
            WHERE p.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}

}
