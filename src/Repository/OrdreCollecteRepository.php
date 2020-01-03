<?php

namespace App\Repository;

use App\Entity\Collecte;
use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method OrdreCollecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecte[]    findAll()
 * @method OrdreCollecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteRepository extends ServiceEntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'statut',
		'Date' => 'date',
		'Opérateur' => 'utilisateur',
		'Type' => 'type'
	];

	/**
	 * @param Collecte $collecte
	 * @return OrdreCollecte
	 */
    public function findByDemandeCollecte($collecte)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            'SELECT oc
            FROM App\Entity\OrdreCollecte oc
            WHERE oc.demandeCollecte = :collecte'
        )->setParameter('collecte', $collecte);
        return $query->execute();
    }

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws NonUniqueResultException
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
		$entityManager = $this->getEntityManager();
		$query = $entityManager
            ->createQuery($this->getOrdreCollecteQuery() . " WHERE (s.nom = :statutLabel AND (oc.utilisateur IS NULL OR oc.utilisateur = :user))")
            ->setParameters([
                'statutLabel' => $statutLabel,
                'user' => $user,
            ]);
		return $query->execute();
	}

	/**
	 * @param int $ordreCollecteId
	 * @return mixed
	 */
	public function getById($ordreCollecteId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager
            ->createQuery($this->getOrdreCollecteQuery() . " WHERE oc.id = :id")
            ->setParameter('id', $ordreCollecteId);
		$result = $query->execute();
		return !empty($result) ? $result[0] : null;
	}

	private function getOrdreCollecteQuery(): string  {
	    return (/** @lang DQL */
            "SELECT oc.id,
                    oc.numero as number,
                    pc.label as location_from,
                    dc.stockOrDestruct as forStock
            FROM App\Entity\OrdreCollecte oc
            LEFT JOIN oc.demandeCollecte dc
            LEFT JOIN dc.pointCollecte pc
            LEFT JOIN oc.statut s"
        );
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
					$qb
						->join('oc.statut', 's')
						->andWhere('s.nom = :statut')
						->setParameter('statut', $filter['value']);
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
						->andWhere("u.username in (:username)")
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

}
