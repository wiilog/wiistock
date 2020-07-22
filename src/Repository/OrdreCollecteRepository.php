<?php

namespace App\Repository;

use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @method OrdreCollecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecte[]    findAll()
 * @method OrdreCollecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteRepository extends EntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'statut',
		'Date' => 'date',
		'Opérateur' => 'utilisateur',
		'Type' => 'type'
	];

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
			"SELECT COUNT(o)
            FROM App\Entity\OrdreCollecte o
            WHERE o.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param string $statutLabel
	 * @param Utilisateur $user
	 * @return mixed
	 */
	public function getByStatutLabelAndUser($statutLabel, $user)
	{
        $queryBuilder = $this->createOrdreCollecteQueryBuilder()
            ->andWhere('s.nom = :statutLabel')
            ->andWhere('oc.utilisateur IS NULL OR oc.utilisateur = :user')
            ->setParameters([
                'statutLabel' => $statutLabel,
                'user' => $user,
            ]);
		return $queryBuilder->getQuery()->execute();
	}

	/**
	 * @param int $ordreCollecteId
	 * @return mixed
	 */
	public function getById($ordreCollecteId)
	{
		$queryBuilder = $this->createOrdreCollecteQueryBuilder()
            ->andWhere('oc.id = :id')
            ->setParameter('id', $ordreCollecteId);
		$result = $queryBuilder->getQuery()->execute();
		return !empty($result) ? $result[0] : null;
	}

	private function createOrdreCollecteQueryBuilder(): QueryBuilder  {
	    return $this->createQueryBuilder('oc')
            ->select('oc.id')
            ->addSelect('oc.numero as number')
            ->addSelect('pc.label as location_from')
            ->addSelect('dc.stockOrDestruct as forStock')
            ->addSelect('demandeur.username as requester')
            ->addSelect('typeDemandeCollecte.label as type')
            ->addSelect('dc.commentaire as comment')
            ->leftJoin('oc.demandeCollecte', 'dc')
            ->leftJoin('dc.demandeur', 'demandeur')
            ->leftJoin('dc.pointCollecte', 'pc')
            ->leftJoin('oc.statut', 's')
            ->leftJoin('dc.type', 'typeDemandeCollecte');
	}

    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('oc')
            ->from('App\Entity\OrdreCollecte', 'oc');

        $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('oc.statut', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
				case 'type':
					$qb
						->join('oc.demandeCollecte', 'dc')
						->join('dc.type', 't')
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('oc.utilisateur', 'u')
						->andWhere("u.id in (:username)")
						->setParameter('username', $value);
					break;
				case 'dateMin':
					$qb
						->andWhere('oc.date >= :dateMin')
						->setParameter('dateMin', $filter['value'] . ' 00:00:00');
					break;
				case 'dateMax':
					$qb
						->andWhere('oc.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . '23:59:00');
					break;
				case 'demCollecte':
					$value = explode(':', $filter['value'])[0];
					$qb
						->join('oc.demandeCollecte', 'dcb')
						->andWhere('dcb.id = :id')
						->setParameter('id', $value);
					break;
				case 'demandeCollecte':
					$qb
						->join('oc.demandeCollecte', 'dcb')
						->andWhere('dcb.id = :id')
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
                        ->join('oc.statut', 's2')
                        ->join('oc.utilisateur', 'u2')
                        ->join('oc.demandeCollecte', 'dc2')
                        ->join('dc2.type', 't2')
                        ->andWhere('oc.numero LIKE :value
						OR s2.nom LIKE :value
						OR u2.username LIKE :value
						OR t2.label LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];

                if (!empty($order)) {
					$column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

					switch ($column) {
						case 'type':
							$qb
								->leftJoin('oc.demandeCollecte', 'dc3')
								->leftJoin('dc3.type', 't3')
								->orderBy('t3.label', $order);
							break;
						case 'statut':
							$qb
								->leftJoin('oc.statut', 's3')
								->orderBy('s3.nom', $order);
							break;
						case 'utilisateur':
							$qb
								->leftJoin('oc.utilisateur', 'u3')
								->orderBy('u3.username', $order);
							break;
						default:
							$qb->orderBy('oc.' . $column, $order);
							break;
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

		return ['data' => $query ? $query->getResult() : null ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}

	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return OrdreCollecte[]|null
	 */
	public function findByDates($dateMin, $dateMax)
	{
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT oc
            FROM App\Entity\OrdreCollecte oc
            WHERE oc.date BETWEEN :dateMin AND :dateMax'
		)->setParameters([
			'dateMin' => $dateMin,
			'dateMax' => $dateMax
		]);
		return $query->execute();
	}
}
