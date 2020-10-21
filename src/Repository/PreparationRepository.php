<?php

namespace App\Repository;

use App\Entity\FiltreSup;
use App\Entity\Preparation;
use App\Entity\Utilisateur;
use App\Helper\QueryCounter;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @method Preparation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Preparation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Preparation[]    findAll()
 * @method Preparation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationRepository extends EntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'status',
		'Date' => 'date',
		'Opérateur' => 'user',
		'Type' => 'type'
	];

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

    /**
     * @param Utilisateur $user
     * @param array $preparationIdsFilter
     * @return array
     */
    public function getMobilePreparations(Utilisateur $user, array $preparationIdsFilter = [])
    {
        $queryBuilder = $this->createQueryBuilder('p');
        $queryBuilder
            ->select('p.id')
            ->addSelect('p.numero as number')
            ->addSelect('dest.label as destination')
            ->addSelect('user.username as requester')
            ->addSelect('t.label as type')
            ->addSelect('d.commentaire as comment')
            ->join('p.statut', 's')
            ->join('p.demande', 'd')
            ->join('d.destination', 'dest')
            ->join('d.type', 't')
            ->join('d.utilisateur', 'user')
            ->andWhere('s.nom = :toTreatStatusLabel or (s.nom = :inProgressStatusLabel AND p.utilisateur = :user)')
            ->andWhere('t.id IN (:type)')
            ->setParameters([
                'toTreatStatusLabel' => Preparation::STATUT_A_TRAITER,
                'inProgressStatusLabel' => Preparation::STATUT_EN_COURS_DE_PREPARATION,
                'user' => $user,
                'type' => $user->getDeliveryTypeIds()
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
		$qb = $this->createQueryBuilder("p");

		$countTotal = QueryCounter::count($qb, 'p');

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
		$countFiltered = QueryCounter::count($qb, 'p');

		if ($params) {
			if (!empty($params->get('start'))) {
			    $qb->setFirstResult($params->get('start'));
            }
			if (!empty($params->get('length'))) {
			    $qb->setMaxResults($params->get('length'));
            }
		}

		return [
			'data' => $qb->getQuery()->getResult(),
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

    public function getFirstDatePreparationGroupByDemande (array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('preparation')
            ->select('demande.id AS demandeId')
            ->addSelect('MIN(preparation.date) AS firstDate')
            ->join('preparation.demande', 'demande')
            ->where('preparation.demande in (:demandes)')
            ->groupBy('demande.id')
            ->setParameter('demandes', $demandes);

        $lastDatePreparationDemande = $queryBuilder->getQuery()->execute();;
        return array_reduce($lastDatePreparationDemande, function(array $carry, $current) {
            $demandeId = $current['demandeId'];
            $firstDate = $current['firstDate'];

            $carry[$demandeId] = $firstDate;
            return $carry;
        }, []);
    }

    public function getNumeroPrepaGroupByDemande (array $demandes)
    {
        $queryBuilder = $this->createQueryBuilder('preparation')
            ->select('demande.id AS demandeId')
            ->addSelect('preparation.numero AS numeroPreparation')
            ->join('preparation.demande', 'demande')
            ->where('preparation.demande in (:demandes)')
            ->setParameter('demandes', $demandes);

        $result = $queryBuilder->getQuery()->execute();
        return array_reduce($result, function (array $carry, $current) {

            $demandeId = $current['demandeId'];
            $numeroPreparation = $current['numeroPreparation'];
            if (!isset($carry[$demandeId])) {
                $carry[$demandeId] = [];
            }
            $carry[$demandeId][] = $numeroPreparation;
            return $carry;
        }, []);
    }
	public function countByNumero(string $numero) {
	    $queryBuilder = $this
            ->createQueryBuilder('preparation')
            ->select('COUNT(preparation.id) AS counter')
            ->where('preparation.numero = :numero')
            ->setParameter('numero', $numero . '%');

	    $result = $queryBuilder
            ->getQuery()
            ->getResult();

	    return !empty($result) ? ($result[0]['counter'] ?? 0) : 0;
    }

}
